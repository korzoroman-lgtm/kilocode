/**
 * Photo2Video - Main JavaScript
 * Vanilla JavaScript for frontend interactions
 */

class Photo2VideoApp {
    constructor() {
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        this.apiBase = '/api/v1';
        this.init();
    }

    init() {
        this.bindEvents();
        this.initDropdowns();
        this.initModals();
        this.initFlashMessages();
    }

    bindEvents() {
        document.querySelectorAll('form[data-validate]').forEach(form => {
            form.addEventListener('submit', (e) => this.validateForm(e));
        });

        document.querySelectorAll('.file-upload').forEach(upload => {
            this.initFileUpload(upload);
        });

        document.querySelectorAll('a[data-ajax]').forEach(link => {
            link.addEventListener('click', (e) => this.handleAjaxLink(e));
        });

        document.querySelectorAll('[data-confirm]').forEach(element => {
            element.addEventListener('click', (e) => {
                if (!confirm(element.dataset.confirm || 'Are you sure?')) {
                    e.preventDefault();
                }
            });
        });
    }

    initDropdowns() {
        document.querySelectorAll('.dropdown').forEach(dropdown => {
            const toggle = dropdown.querySelector('.dropdown-toggle');
            const menu = dropdown.querySelector('.dropdown-menu');

            if (toggle && menu) {
                toggle.addEventListener('click', (e) => {
                    e.preventDefault();
                    document.querySelectorAll('.dropdown.active').forEach(d => {
                        if (d !== dropdown) d.classList.remove('active');
                    });
                    dropdown.classList.toggle('active');
                });

                document.addEventListener('click', (e) => {
                    if (!dropdown.contains(e.target)) {
                        dropdown.classList.remove('active');
                    }
                });
            }
        });
    }

    initModals() {
        document.querySelectorAll('[data-modal]').forEach(trigger => {
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                const modalId = trigger.dataset.modal;
                const modal = document.getElementById(modalId);
                if (modal) {
                    this.openModal(modal);
                }
            });
        });

        document.querySelectorAll('.modal-overlay, .modal-close').forEach(element => {
            element.addEventListener('click', (e) => {
                if (e.target === element) {
                    element.closest('.modal-overlay').classList.remove('active');
                }
            });
        });
    }

    openModal(modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    closeModal(modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }

    initFileUpload(container) {
        const input = container.querySelector('input[type="file"]');
        const preview = container.querySelector('.upload-preview');
        const progress = container.querySelector('.upload-progress');

        if (!input) return;

        input.addEventListener('change', async (e) => {
            const file = e.target.files[0];
            if (!file) return;

            const maxSize = parseInt(container.dataset.maxSize || '10485760');
            const allowedTypes = (container.dataset.types || 'image/jpeg,image/png,image/webp').split(',');

            if (file.size > maxSize) {
                this.showError('File too large');
                return;
            }

            if (!allowedTypes.includes(file.type)) {
                this.showError('Invalid file type');
                return;
            }

            if (file.type.startsWith('image/') && preview) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
                };
                reader.readAsDataURL(file);
            }

            if (container.dataset.upload && progress) {
                await this.uploadFile(container, file, progress);
            }
        });
    }

    async uploadFile(container, file, progress) {
        const endpoint = container.dataset.upload;
        const formData = new FormData();
        formData.append('file', file);

        progress.style.display = 'block';
        const progressBar = progress.querySelector('.progress-bar');
        if (progressBar) progressBar.style.width = '0%';

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': this.csrfToken
                },
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                if (progressBar) progressBar.style.width = '100%';
                this.showSuccess('Upload complete');
                container.dispatchEvent(new CustomEvent('uploaded', { detail: data }));
            } else {
                this.showError(data.message || 'Upload failed');
            }
        } catch (error) {
            this.showError('Upload failed: ' + error.message);
        }
    }

    async handleAjaxLink(e) {
        e.preventDefault();
        const link = e.currentTarget;
        const url = link.href;
        const method = link.dataset.method || 'POST';

        try {
            const response = await fetch(url, {
                method: method,
                headers: {
                    'X-CSRF-Token': this.csrfToken
                }
            });

            const data = await response.json();

            if (data.success) {
                link.dispatchEvent(new CustomEvent('ajax-success', { detail: data }));
                this.showSuccess(data.message || 'Done');
            } else {
                this.showError(data.message || 'Request failed');
            }
        } catch (error) {
            this.showError('Request failed: ' + error.message);
        }
    }

    validateForm(e) {
        const form = e.target;
        let valid = true;

        form.querySelectorAll('[required]').forEach(input => {
            if (!input.value.trim()) {
                valid = false;
                this.markError(input, 'This field is required');
            }
        });

        form.querySelectorAll('input[type="email"]').forEach(input => {
            if (input.value && !this.isValidEmail(input.value)) {
                valid = false;
                this.markError(input, 'Invalid email address');
            }
        });

        form.querySelectorAll('input[type="password"]').forEach(input => {
            if (input.value && input.value.length < 8) {
                valid = false;
                this.markError(input, 'Password must be at least 8 characters');
            }
        });

        if (!valid) {
            e.preventDefault();
        }
    }

    markError(input, message) {
        input.classList.add('error');
        let errorEl = input.parentElement.querySelector('.form-error');
        if (!errorEl) {
            errorEl = document.createElement('div');
            errorEl.className = 'form-error';
            input.parentElement.appendChild(errorEl);
        }
        errorEl.textContent = message;
    }

    clearErrors(form) {
        form.querySelectorAll('.error').forEach(el => el.classList.remove('error'));
        form.querySelectorAll('.form-error').forEach(el => el.remove());
    }

    isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    initFlashMessages() {
        document.querySelectorAll('.flash-message').forEach(message => {
            setTimeout(() => {
                message.style.opacity = '0';
                setTimeout(() => message.remove(), 300);
            }, 5000);
        });
    }

    showSuccess(message) {
        this.showNotification(message, 'success');
    }

    showError(message) {
        this.showNotification(message, 'error');
    }

    showNotification(message, type) {
        const container = document.getElementById('notifications') || this.createNotificationContainer();
        const notification = document.createElement('div');
        notification.className = 'notification notification-' + type;
        notification.textContent = message;
        container.appendChild(notification);

        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }

    createNotificationContainer() {
        const container = document.createElement('div');
        container.id = 'notifications';
        container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999;';
        document.body.appendChild(container);
        return container;
    }

    async api(endpoint, options = {}) {
        const url = this.apiBase + endpoint;
        const headers = Object.assign({
            'Content-Type': 'application/json',
            'X-CSRF-Token': this.csrfToken
        }, options.headers);

        const response = await fetch(url, Object.assign({ method: 'GET' }, options, { headers }));
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'API request failed');
        }

        return data;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    window.app = new Photo2VideoApp();
});
