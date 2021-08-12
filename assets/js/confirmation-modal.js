import Modal from 'bootstrap/js/dist/modal';

export default class ConfirmationModal {
    create(element) {
        element.addEventListener('click', (event) => {
            event.preventDefault();

            const confirmationActionButton = document.querySelector('#protung-easyadmin-plus-extension-modal-action-custom-confirmation-button');
            const confirmationActionTitle = document.querySelector('#protung-easyadmin-plus-extension-modal-action-custom-confirmation-title');
            const confirmationActionDescription = document.querySelector('#protung-easyadmin-plus-extension-modal-action-custom-confirmation-description');

            const title = element.getAttribute('data-protung-easyadmin-plus-extension-modal-confirm-title');
            const description = element.getAttribute('data-protung-easyadmin-plus-extension-modal-confirm-description');

            confirmationActionTitle.textContent = title;
            confirmationActionDescription.textContent = description;

            if (element.tagName === 'BUTTON') {
                confirmationActionButton.click(
                    function () {
                        element.closest('form').submit();
                    }
                );
            } else {
                // assume link
                confirmationActionButton.setAttribute('href', element.getAttribute('href'));
            }

            confirmationActionButton.setAttribute('class', 'btn btn-' + (element.getAttribute('data-protung-easyadmin-plus-extension-modal-confirm-type') ?? 'primary'));

            const confirmationActionModal = new Modal(
                document.querySelector('#protung-easyadmin-plus-extension-modal-action-custom-confirmation'),
                {
                    backdrop: true,
                    keyboard: true
                }
            );

            confirmationActionModal.show()
        });
    }
}
