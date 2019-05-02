import Backbone from 'backbone';

export default Backbone.Model.extend({
    defaults: function() {
        return {
            id: null,
            cdnUrl: null,
            width: null,
            height: null,
            sourceId: null,
            sourceName: null,
            baseUrl: null,
            postId: null,
            title: null,
            dateCreated: null,
            externalId: null,
            score: null,
            userId: null,
            userName: null,
            nsfw: null,
            thumb: null,
            idxInAlbum: null,
            age: null,
            rendered: false,
            visible: false,
            distance: null,
            caption: null,
            sourceUrl: null,
            userVote: null
        };
    }

});