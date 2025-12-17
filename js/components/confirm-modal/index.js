import { render, createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import MeprModal from './view';
import './index.scss';

const { applyFilters, doAction } = wp.hooks;

// Create root if not exists.
const ensureRoot = () => {
    let root = document.getElementById('mepr-modal-root');
    if (!root) {
        root = document.createElement('div');
        root.id = 'mepr-modal-root';
        document.body.appendChild(root);
    }
    return root;
};

// trim response.
const trimResponse = (response) => {
    return response.replace(/^\s+|\s+$/g, '');
};

// Expose global modal manager.
window.MeprModal = ({
    title,
    message,
    confirmText,
    cancelText,
    isDestructive = false,
    ajaxData,
    onConfirm,
    onSuccess,
    onError,
    modalContext
}) => {

    const root = ensureRoot();

    const close = () => {
        render(null, root);
    };

    modalContext = modalContext || ajaxData.action;
    title   = applyFilters(`meprModal.${modalContext}.title`, title);
    message = applyFilters(`meprModal.${modalContext}.message`, message);

    const handleConfirm = async () => {
        // If there's a custom onConfirm callback, call it.
        if (onConfirm) {
            try {
                await onConfirm();
            } catch (error) {
                console.error('Modal confirm callback error:', error);
            }
            close();
            return;
        }

        // Handle AJAX request if ajaxAction is provided.
        if (ajaxData) {
            try {
                ajaxData = applyFilters(`MeprModal.${modalContext}.ajaxData`, ajaxData);

                const formData = new FormData();
                Object.keys(ajaxData).forEach(key => {
                    formData.append(key, ajaxData[key]);
                });

                const response = await fetch(window.ajaxurl || ajaxurl, {
                    method: 'POST',
                    body: formData
                });

                const responseText = await response.text();
                const trimmedData = trimResponse(responseText);

                if (trimmedData === 'true' || response.ok) {
                    doAction(`meprModal.${modalContext}.success`, {
                        ajaxData: ajaxData,
                        response: trimmedData
                    });
                    if (onSuccess) {
                        await onSuccess(trimmedData);
                    }
                    close();
                    return;
                } else {
                    if (onError) {
                        onError(trimmedData);
                    }
                    close();
                    return;
                }
            } catch (error) {
                throw new Error(error.message);
            }
        }
    };

    render(
        createElement(MeprModal, {
            title,
            message,
            confirmText,
            cancelText,
            isDestructive,
            onConfirm: handleConfirm,
            onCancel: () => {
                close();
            },
            modalContext,
        }),
        root
    );
};
