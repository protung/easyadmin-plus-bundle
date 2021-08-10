import '../css/app.css';

import ConfirmationModal from './confirmation-modal';

document.addEventListener('DOMContentLoaded', () => {
    App.createConfirmationActionModal();
});

const App = (() => {
    const createConfirmationActionModal = () => {
        const confirmationModal = new ConfirmationModal();
        document.querySelectorAll("[data-protung-easyadmin-plus-extension-modal-confirm-trigger='1']").forEach((action) => {
            confirmationModal.create(action);
        });
    };

    return {
        createConfirmationActionModal: createConfirmationActionModal,
    };
})();
