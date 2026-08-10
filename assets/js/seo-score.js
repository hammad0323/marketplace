/**
 * Live client-side mirror of includes/functions.php::seo_score_for_medicine().
 * Keep the weightings identical in both places — this is the number the user
 * watches update as they type; the PHP version is the one actually saved.
 *
 * Usage: initSeoScore({ name: '#field', keyword: '#field', metaTitle: '#field',
 *   metaDescription: '#field', getContent: function () { return string }, bar: '#bar', label: '#label' });
 */
(function () {
    'use strict';

    function wordCount(str) {
        str = (str || '').trim();
        if (!str) return 0;
        return str.split(/\s+/).length;
    }

    function computeScore(o) {
        var name = (o.name || '').trim();
        var keyword = (o.keyword || '').trim();
        var metaTitle = (o.metaTitle || '').trim();
        var metaDescription = (o.metaDescription || '').trim();
        var content = (o.content || '').replace(/<[^>]*>/g, ' ').trim();
        var kwLower = keyword.toLowerCase();
        var score = 0;

        if (keyword) score += 5;
        if (metaTitle) score += 10;
        if (metaTitle && keyword && metaTitle.toLowerCase().indexOf(kwLower) !== -1) score += 10;
        if (metaTitle.length >= 30 && metaTitle.length <= 60) score += 5;
        if (metaDescription) score += 10;
        if (metaDescription && keyword && metaDescription.toLowerCase().indexOf(kwLower) !== -1) score += 10;
        if (metaDescription.length >= 120 && metaDescription.length <= 160) score += 5;
        var words = wordCount(content);
        if (words >= 300) score += 20;
        else if (words >= 150) score += 10;
        if (content && keyword && content.toLowerCase().indexOf(kwLower) !== -1) score += 15;
        if (name && keyword && name.toLowerCase().indexOf(kwLower) !== -1) score += 10;

        return Math.min(100, score);
    }

    function bandFor(score) {
        if (score >= 80) return { color: '#22C55E', label: 'Good' };
        if (score >= 50) return { color: '#F59E0B', label: 'OK' };
        return { color: '#EF4444', label: 'Needs work' };
    }

    window.initSeoScore = function (opts) {
        var $ = window.jQuery;
        var $name = $(opts.name);
        var $keyword = $(opts.keyword);
        var $metaTitle = $(opts.metaTitle);
        var $metaDescription = $(opts.metaDescription);
        var $bar = $(opts.bar);
        var $label = $(opts.label);

        function update() {
            var score = computeScore({
                name: $name.val(),
                keyword: $keyword.val(),
                metaTitle: $metaTitle.val(),
                metaDescription: $metaDescription.val(),
                content: typeof opts.getContent === 'function' ? opts.getContent() : '',
            });
            var band = bandFor(score);
            $bar.css({ width: score + '%', background: band.color });
            $label.text(score + '/100 — ' + band.label);
        }

        $name.add($keyword).add($metaTitle).add($metaDescription).on('input change', update);
        if (opts.watchExtra) $(opts.watchExtra).on('input change', update);
        update();
        return update;
    };
})();
