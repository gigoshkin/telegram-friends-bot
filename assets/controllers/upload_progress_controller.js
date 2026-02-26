// assets/controllers/upload_progress_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["container", "bar", "percent"];

    submit(event) {
        const form = this.element;
        const submitButton = form.querySelector('button[type="submit"]');
        const formData = new FormData(form);
        const xhr = new XMLHttpRequest();

        event.preventDefault();

        if (submitButton) submitButton.disabled = true;
        this.containerTarget.classList.add('is-visible');

        xhr.upload.addEventListener("progress", (e) => {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                this.barTarget.style.width = `${percent}%`;
                this.percentTarget.innerText = percent;
            }
        });

        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.responseText);

                    if (response.targetUrl) {
                        window.location.href = response.targetUrl;
                    } else {
                        window.location.reload();
                    }
                } catch (e) {
                    window.location.reload();
                }
            } else {
                alert("Upload failed. Please check the file format.");
                if (submitButton) submitButton.disabled = false;
                this.containerTarget.classList.remove('is-visible');
            }
        };

        xhr.open("POST", form.action || window.location.href);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.send(formData);
    }
}
