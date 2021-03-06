<?php

namespace Lib {

    use Api;
    use stdClass;

    define('IMAGE_TYPE_JPEG', 'jpg');
    define('IMAGE_TYPE_PNG', 'png');
    define('IMAGE_TYPE_GIF', 'gif');
    define('IMAGE_TYPE_MAX_LENGTH', 3);
    define('IMAGE_CACHE_EXT', 'csh');

    define('IMGEVT_DOWNLOAD_BEGIN', 'IMGEVT_DOWNLOAD_BEGIN');
    define('IMGEVT_DOWNLOAD_COMPLETE', 'IMGEVT_DOWNLOAD_COMPLETE');
    define('IMGEVT_DOWNLOAD_ERROR', 'IMGEVT_DOWNLOAD_ERROR');

    define('CACHE_MAX_SIZE', 16 * 1024 * 1024);

    class ImageLoader {

        private static $REDDIT_IMAGE_MIME_TO_EXT = [
            'image/jpg' => 'jpg',
            'image/gif' => 'gif',
            'image/png' => 'png'
        ];

        /**
         * Based on the incoming URL, resolves against various services and provides back and array of image URLs
         */
        public static function getImagesFromUrl($url) {

            $parts = parse_url($url);
            $domain = isset($parts['host']) ? strtolower($parts['host']) : null;
            $domain = explode('.', $domain, 2);
            $domain = strpos($domain[1], '.') === false ? join('.', $domain) : end($domain);
            $path = isset($parts['path']) ? $parts['path'] : null;
            $retVal = [];

            // Imgur links
            if ('imgur.com' === $domain) {
                $retVal = self::handleImgurLink($url);

            } else if ('redditbooru.com' === $domain) {
                // Temporary solution to deal with data coming from PROD
                $retVal = self::getRedditBooruImages($url);

            // DeviantArt images
            } else if ('deviantart.com' === $domain || 'fav.me' === $domain) {
                $retVal[] = self::getDeviantArtImage($url);

            // Yandere image
            } else if ($domain === 'yande.re') {
                $retVal = self::getYandereImage($url);

            // Minus album
            } else if (preg_match('/([\w]+\.)?minus\.com\/([^\.])+$/i', $url, $matches)) {
                $retVal = self::getMinusAlbum($matches[2]);

            // tumblr posts
            } else if ('tumblr.com' === $domain && preg_match('/\/post\/([\d]+)\//', $path, $matches)) {
                $retVal = self::getTumblrImages($url);

            // mediacrush
            } else if ($domain === 'mediacru.sh') {
                $retVal = self::getMediacrushImages($url);

            // gfycat
            } else if ($domain === 'gfycat.com' && strpos($path, '.gif') === false) {
                $retVal[] = self::getGfycatImage($url);

            // Twitter
            } else if ($domain === 'twitter.com' && strpos($path, '/status/') !== false) {
                $retVal[] = self::getTwitterImage($url);

            } else if ($domain === 'reddit.com' && strpos($path, '/gallery/') !== false) {
                $retVal = self::getRedditGallery($url);

            // Everything else
            } else {
                $retVal[] = $url;
            }

            return $retVal;

        }

        /**
         * Resolves a deviantArt link to it's image
         */
        public static function getDeviantArtImage($url) {
            $retVal = null;
            $info = json_decode(Http::get('http://backend.deviantart.com/oembed?url=' . urlencode($url)));
            if (is_object($info)) {
                $retVal = $info->url;
            }
            return $retVal;
        }

        /**
         * Handles image fetching on various formats of imgur links
         */
        public static function handleImgurLink($url) {
            $parts = parse_url($url);
            $path = $parts['path'];
            $retVal = [];

            if (strpos($path, ',') !== false) {
                $path = str_replace('/', '', $path);
                $files = explode(',', $path);
                foreach ($files as $file) {
                    $retVal[] = 'http://imgur.com/' . $file . '.jpg';
                }
            } else if (strpos($path, '/a/') === 0 || strpos($path, '/gallery/') === 0) {
                $id = str_replace([ '/a/', '/gallery/' ], '', $path);
                $id = explode('#', $id);
                $id = current($id);
                $retVal = self::getImgurAlbum($id);
            } else if (strpos($path, '.') === false) {
                $retVal[] = $url .= '.jpg';
            } else {
                $retVal[] = $url;
            }

            return $retVal;
        }

        /**
         * Gets a list of image URLs from an imgur album
         */
        public static function getImgurAlbum($id) {
            $data = Http::get('https://api.imgur.com/3/album/' . $id . '.json', [ 'Authorization: Client-ID ' . IMGUR_CLIENT_ID ]);
            $retVal = null;
            if (strlen($data) > 0) {
                $data = json_decode($data);
                if (isset($data->data) && isset($data->data->images)) {
                    $retVal = [];
                    foreach ($data->data->images as $image) {
                        $retVal[] = $image->link;
                    }
                }
            }
            return $retVal;
        }

        /**
         * Returns a list of image URLs in a tumblr post
         */
        public static function getTumblrImages($url) {
            $retVal = [];

            // Parse out the ID
            $url = parse_url($url);
            if (preg_match('/\/post\/([\d]+)\//', $url['path'], $matches)) {
                $apiCall = 'http://api.tumblr.com/v2/blog/' . $url['host'] . '/posts?id=' . $matches[1] . '&api_key=' . TUMBLR_CONSUMER_KEY;
                $response = json_decode(Http::get($apiCall));
                if ($response && is_object($response->response)) {
                    foreach ($response->response->posts[0]->photos as $photo) {
                        $retVal[] = $photo->original_size->url;
                    }
                }
            }

            return $retVal;
        }

        public static function getYandereImage($url) {
            $retVal = [];
            $response = Http::get($url);
            if (preg_match('/original-file-changed\" href=\"([^\"]+)\"/', $response, $match)) {
                $retVal[] = $match[1];
            }
            return $retVal;
        }

        /**
         * Returns a list of image URLs in a mediacrush post
         */
        public static function getMediacrushImages($url) {
            $retVal = [];

            $response = json_decode(Http::get($url . '.json'));
            if (is_object($response) && is_array($response->files)) {
                $url = parse_url($url);
                if (count($response->files) === 1) {
                    $retVal[] = $url['scheme'] . '://mediacru.sh' . $response->original;
                } else {
                    foreach ($response->files as $file) {
                        $retVal[] = $url['scheme'] . '://mediacru.sh' . $file->original;
                    }
                }
            }

            return $retVal;
        }

        /**
         * Scrapes a minus album page and gets the URLs for all images
         */
        public static function getMinusAlbum($id) {

            $retVal = null;

            $page = Http::get('http://minus.com/' . $id);
            if ($page) {

                // Get the image data json
                $dataBeginToken = 'var gallerydata = ';
                $dataEndToken = '};';
                $start = strpos($page, $dataBeginToken);
                if (false !== $start) {
                    $end = strpos($page, $dataEndToken, $start) + 1;
                    $start += strlen($dataBeginToken);
                    $jsonData = json_decode(substr($page, $start, $end - $start));
                    if (is_object($jsonData) && is_array($jsonData->items)) {
                        $retVal = [];
                        foreach ($jsonData->items as $item) {
                            $ext = explode('.', $item->name);
                            $ext = end($ext);
                            $retVal[] = 'http://i.minus.com/i' . $item->id . '.' . $ext;
                        }
                    }
                }

            }

            return $retVal;

        }

        /**
         * Retrieves images from a redditbooru album
         */
        public static function getRedditBooruImages($url) {
            $retVal = null;

            // For testing purposes, we'll use a call to the API. In final version, this will be a database call
            $id = Api\Post::getPostIdFromUrl($url);
            if ($id) {
                $sub = strpos($url, 'beta.') !== false ? 'beta.' : '';
                $data = Http::get('https://' . $sub . 'redditbooru.com/images/?postId=' . $id);
                if ($data) {
                    $data = json_decode($data);
                    if (count($data) > 0) {
                        $retVal = [];
                        foreach ($data as $image) {
                            $retVal[] = $image->cdnUrl;
                        }
                    }
                }
            }

            return $retVal;
        }

        /**
         * Gets the GIF version of a gfycat image
         */
        public static function getGfycatImage($url) {
            $parts = explode('/', $url);
            $name = end($parts);
            $retVal = null;

            // Make sure we don't get any hash shit
            $name = explode('#', $name)[0];

            $data = file_get_contents('http://gfycat.com/cajax/get/' . $name);
            if ($data) {
                $data = json_decode($data);
                if ($data && isset($data->gfyItem)) {
                    $retVal = $data->gfyItem->gifUrl;
                }
            }

            return $retVal;
        }

        /**
         * Fetches an image from a tweet
         */
        public static function getTwitterImage($url) {
            $retVal = null;
            $data = @file_get_contents($url);
            if ($data && preg_match('/data-image-url=\"([^\"]+)\"/', $data, $matches)) {
                $retVal = $matches[1];
            }
            return $retVal;
        }

        /**
         * Fetches an array of image URLs from a reddit gallery
         */
        public static function getRedditGallery($url) {
            $retVal = [ $url ];
            preg_match('/\/gallery\/([\w]+)/is', $url, $matches);
            if ($matches) {
                $id = $matches[1];

                $response = @file_get_contents('https://www.reddit.com/api/info/.json?id=t3_' . $id);
                $data = json_decode($response);

                // ...ugh. the API for galleries is seemingly non-existent right now, so here we are
                if (
                    $data &&
                    $data->data &&
                    is_array($data->data->children) &&
                    $data->data->children[0]->data->gallery_data &&
                    $data->data->children[0]->data->media_metadata
                ) {
                    $retVal = [];
                    foreach ($data->data->children[0]->data->media_metadata as $imageId => $image) {
                        if ($image->status === 'valid') {
                            $retVal[] = 'https://i.redd.it/' . $imageId . '.' . self::$REDDIT_IMAGE_MIME_TO_EXT[$image->m];

                        }
                    }
                }
            }

            return $retVal;
        }

        /**
         * Given a file, returns the image mime type
         */
        public static function getImageType($fileName) {
            $retVal = null;
            $handle = fopen($fileName, 'rb');
            if ($handle) {
                $head = fread($handle, 10);
                $retVal = self::_getImageType($head);
                fclose($handle);
            }
            return $retVal;
        }

        /**
         * Determines the image type of the incoming data
         * @param string $data Data of the image file to determine
         * @return string Mime type of the image, null if not recognized
         */
        private static function _getImageType($data) {

            $retVal = null;
            if (ord($data{0}) == 0xff && ord($data{1}) == 0xd8) {
                $retVal = IMAGE_TYPE_JPEG;
            } else if (ord($data{0}) == 0x89 && substr($data, 1, 3) == 'PNG') {
                $retVal = IMAGE_TYPE_PNG;
            } else if (substr($data, 0, 6) == 'GIF89a' || substr($data, 0, 6) == 'GIF87a') {
                $retVal = IMAGE_TYPE_GIF;
            }

            return $retVal;

        }

        /**
         * Loads a file, determines the image type by scanning the header, and returns a GD object
         * @param string $file Path to the file to load
         * @return object Object containing the GD image and the mimeType, null on failure
         */
        public static function loadImage($file) {

            $retVal = null;

            $type = self::getImageType($file);

            if (false !== $type) {
                $retVal = new stdClass;
                $retVal->mimeType = $type;
                switch ($type) {
                    case IMAGE_TYPE_JPEG:
                        $retVal->image = @imagecreatefromjpeg($file);
                        break;
                    case IMAGE_TYPE_PNG:
                        $retVal->image = @imagecreatefrompng($file);
                        break;
                    case IMAGE_TYPE_GIF:
                        $retVal->image = @imagecreatefromgif($file);
                        break;
                    default:
                        $retVal = null;
                }

                if (null != $retVal && null == $retVal->image) {
                    $retVal = null;
                }

            }

            return $retVal;

        }

        private static function _downloadImage($url) {

            $retVal = null;

            // Account for local files or URLs
            if ($url{0} === '/' && is_readable($url) || strpos($url, 'file://') === 0) {
                $file = file_get_contents($url);
            } else {
                $file = Http::get($url);
            }

            if (!$file) {
                Events::fire(IMGEVT_DOWNLOAD_ERROR, 'Unable to download file');
            } else {
                $type = self::_getImageType($file);

                if (null !== $type) {

                    $retVal = new stdClass;
                    $retVal->type = $type;
                    $retVal->timestamp = time();
                    $retVal->data = $file;

                    // Cache the image
                    self::_saveCacheFile($url, $retVal);


                } else {
                    Events::fire(IMGEVT_DOWNLOAD_ERROR, 'Invalid image type');
                }
            }

            return $retVal;

        }

        /**
         * Attempts to retrieve an image from long term cache
         */
        private static function _fetchFromCache($url) {

            $retVal = null;

            $cacheFile = THUMBNAIL_STORAGE . md5($url) . '.' . IMAGE_CACHE_EXT;
            if (is_readable($cacheFile)) {
                $file = fopen($cacheFile, 'rb');
                if ($file) {
                    $retVal = new stdClass;
                    $retVal->type = fread($file, IMAGE_TYPE_MAX_LENGTH);
                    $retVal->timestamp = filemtime($cacheFile);
                    $retVal->data = fread($file, filesize($cacheFile) - IMAGE_TYPE_MAX_LENGTH);
                    fclose($file);
                }
            } else {
                $retVal = self::_downloadImage($url);
            }

            return $retVal;
        }

        /**
         * Saves image data to a cache file
         */
        private static function _saveCacheFile($url, $data) {
            $cacheFile = THUMBNAIL_STORAGE . md5($url) . '.' . IMAGE_CACHE_EXT;
            $file = fopen($cacheFile, 'cb');
            if ($file) {
                fwrite($file, $data->type);
                fwrite($file, $data->data);
                fclose($file);
            }
        }

        /**
         * Given a URL, downloads and saves the output. Does some special case processing depending on where the image is hosted
         * @param string $url URL to download
         * @return object Object containing the image data and type
         */
        public static function fetchImage($url) {

            $retVal = null;

            $startTime = microtime(true);

            // Because SSL is handled at the load balancer level,
            // strip the SLL off of anything being fetched internally
            if (strpos($url, CDN_BASE_URL) === 0) {
                $url = str_replace('https', 'http', $url);
            }

            Events::fire(IMGEVT_DOWNLOAD_BEGIN);

            // Check the cache for a copy of the image
            if (null == $retVal) {
                $retVal = self::_fetchFromCache($url);
            }

            // If nothing was found in the cache, go fetch it the old fashioned way
            if (null === $retVal) {
                $retVal = self::_downloadImage($url);
            }


            if (null !== $retVal) {
                Events::fire(IMGEVT_DOWNLOAD_COMPLETE);
            }

            // Track the download time
            $loadTime = microtime(true) - $startTime;
            Ga::sendEvent(
                'image',
                'fetch',
                null,
                $loadTime
            );
            Api\Tracking::trackEvent('fetch_image', [
                'loadTime' => $loadTime,
                'imageUrl' => $url
            ]);

            return $retVal;

        }

    }

}
