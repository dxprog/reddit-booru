import Handlebars from 'handlebars/runtime';
import _ from 'underscore';

var MINUTE_SECONDS = 60,
    HOUR_SECONDS = MINUTE_SECONDS * 60,
    DAY_SECONDS = HOUR_SECONDS * 24,
    MONTH_SECONDS = DAY_SECONDS * 30,
    YEAR_SECONDS = MONTH_SECONDS * 12;

Handlebars.registerHelper('relativeTime', function(dateStamp) {
    var delta = Math.round(Date.now() / 1000) - dateStamp,
        unit = 'second',
        amount = delta;

    if (delta >= YEAR_SECONDS) {
        amount = Math.floor(delta / YEAR_SECONDS);
        unit = 'year';
    } else if (delta >= MONTH_SECONDS) {
        amount = Math.floor(delta / MONTH_SECONDS);
        unit = 'month';
    } else if (delta >= DAY_SECONDS) {
        amount = Math.floor(delta / DAY_SECONDS);
        unit = 'day';
    } else if (delta >= HOUR_SECONDS) {
        amount = Math.floor(delta / HOUR_SECONDS);
        unit = 'hour';
    } else if (delta >= MINUTE_SECONDS) {
        amount = Math.floor(delta / MINUTE_SECONDS);
        unit = 'minute';
    }

    return amount + ' ' + unit + (amount !== 1 ? 's' : '');

});

Handlebars.registerHelper('voteStatus', function(vote) {
    return vote === -1 ? 'downvote' :
           vote === 1 ? 'upvote' :
           vote === 0 ? 'no-vote' : 'no-data';
});

Handlebars.registerHelper('inTestBucket', function(a, b, c) {
    if (_.has(window.tests, a.hash.key)) {
        return window.tests[a.hash.key] === a.hash.value ? a.fn(this) : '';
    }
});

Handlebars.registerHelper('sep', function(context) {
    if (_.isObject(context) && !context.data.last) {
        return context.fn(context, {});
    }
});

// Lazily adding an export so this can be included and picked up during compile-time
// TODO - something less shitty
export default null;