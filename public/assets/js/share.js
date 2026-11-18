(function () {
    'use strict';

    function initialize(root) {
        var scope = root && typeof root.querySelectorAll === 'function' ? root : document;
        scope.querySelectorAll('[data-share-actions]').forEach(function (component) {
            if (component.dataset.shareInitialized === '1') {
                return;
            }
            component.dataset.shareInitialized = '1';

            var title = component.dataset.shareTitle || '';
            var text = component.dataset.shareText || '';
            var url = component.dataset.shareUrl || '';
            var shareButton = component.querySelector('[data-share-native]');
            var copyButton = component.querySelector('[data-share-copy]');
            var urlInput = component.querySelector('[data-share-url-input]');
            var status = component.querySelector('[data-share-status]');

            function manualCopy(message) {
                if (urlInput) {
                    urlInput.focus();
                    urlInput.select();
                }
                if (status) {
                    status.textContent = message;
                }
            }

            if (shareButton && typeof navigator.share === 'function') {
                shareButton.hidden = false;
                shareButton.addEventListener('click', function () {
                    navigator.share({ title: title, text: text, url: url }).catch(function (error) {
                        if (error && error.name === 'AbortError') {
                            return;
                        }
                        if (status) {
                            status.textContent = 'Não foi possível compartilhar agora. Você ainda pode copiar o link.';
                        }
                    });
                });
            }

            if (copyButton) {
                copyButton.hidden = false;
                copyButton.addEventListener('click', function () {
                    if (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function') {
                        manualCopy('Copie o link manualmente.');
                        return;
                    }

                    navigator.clipboard.writeText(url).then(function () {
                        if (status) {
                            status.textContent = 'Link copiado.';
                        }
                    }).catch(function () {
                        manualCopy('Não foi possível copiar automaticamente. Copie o link manualmente.');
                    });
                });
            }
        });
    }

    window.FlickaryShare = { init: initialize };
    document.addEventListener('DOMContentLoaded', function () { initialize(document); });
    document.addEventListener('flickary:navigated', function (event) {
        initialize(event.target instanceof Element ? event.target : document);
    });
}());
