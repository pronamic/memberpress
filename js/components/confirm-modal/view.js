import { Modal, Button, Spinner } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const { applyFilters } = wp.hooks;

const MeprModal = ({ title, message, confirmText, cancelText, onConfirm, onCancel, isDestructive, modalContext }) => {
    const [loading, setLoading] = useState(false);

    const handleConfirm = async () => {
        setLoading(true);
        try {
            await onConfirm();
        } catch (error) {
            console.error('Modal confirm error:', error);
        } finally {
            setLoading(false);
        }
    };

    return (
        <Modal
            title={title || __('Confirm Action', 'memberpress')}
            onRequestClose={onCancel}
            shouldCloseOnClickOutside={true}
            shouldCloseOnEscKey={true}
            focusOnMount={true}
            size="medium"
            className="mepr-modal"
        >
            <div className="mepr-modal-message">
                <p>{message}</p>
            </div>
            {applyFilters(`meprModal.${modalContext}.before-actions`, null)}
            <div className="mepr-modal-actions">
                <Button
                    variant="secondary"
                    onClick={onCancel}
                    disabled={loading}
                >
                    {cancelText || __('Cancel', 'memberpress')}
                </Button>

                <Button
                    variant="primary"
                    isDestructive={isDestructive}
                    disabled={loading}
                    onClick={handleConfirm}
                >
                    {loading ? <Spinner /> : (confirmText || __('Confirm', 'memberpress'))}
                </Button>
            </div>
        </Modal>
    );
};

export default MeprModal;
