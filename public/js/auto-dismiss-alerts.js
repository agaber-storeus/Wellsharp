(function () {
    var dismissAfter = 5000;
    var selectors = '.alert, .message-bar, .login-error';

    function isHidden(alert) {
        return alert.hidden || alert.classList.contains('is-hidden') || alert.hasAttribute('x-cloak') || alert.style.display === 'none';
    }

    function scheduleAlert(alert) {
        if (!(alert instanceof HTMLElement) || !alert.matches(selectors) || isHidden(alert) || alert.dataset.autoDismissScheduled === 'true') return;

        alert.dataset.autoDismissScheduled = 'true';
        window.setTimeout(function () {
            if (!alert.isConnected) return;
            alert.classList.add('is-dismissing');
            window.setTimeout(function () { alert.remove(); }, 500);
        }, dismissAfter);
    }

    function scheduleDismissal(root) {
        if (root instanceof HTMLElement && root.matches(selectors)) scheduleAlert(root);
        if (root && root.querySelectorAll) root.querySelectorAll(selectors).forEach(scheduleAlert);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { scheduleDismissal(document); });
    } else {
        scheduleDismissal(document);
    }

    window.WellsharpAlerts = { schedule: scheduleAlert, scheduleAll: scheduleDismissal };

    new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            if (mutation.type === 'childList') mutation.addedNodes.forEach(scheduleDismissal);
            if (mutation.type === 'attributes') scheduleAlert(mutation.target);
        });
    }).observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'hidden', 'style', 'x-cloak'] });
})();
