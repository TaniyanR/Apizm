(function () {
    function isInternalLink(link) {
        try {
            return link.origin === window.location.origin;
        } catch (e) {
            return false;
        }
    }

    function buildPayload(link) {
        var block = '';
        var current = link;
        while (current && current !== document.body) {
            if (current.dataset && current.dataset.trackBlock) {
                block = current.dataset.trackBlock;
                break;
            }
            current = current.parentElement;
        }

        var articleId = document.body ? document.body.getAttribute('data-article-id') : '';

        return {
            event_type: 'click',
            from_page: window.location.pathname,
            from_block: block,
            to_url: link.href,
            article_id: articleId ? Number(articleId) : null
        };
    }

    function sendEvent(link) {
        var payload = buildPayload(link);
        if (!navigator.sendBeacon) {
            return;
        }
        var blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
        navigator.sendBeacon('/track.php', blob);
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        if (!target) {
            return;
        }
        var link = target.closest('a');
        if (!link) {
            return;
        }
        if (!isInternalLink(link)) {
            return;
        }
        sendEvent(link);
    });
})();
