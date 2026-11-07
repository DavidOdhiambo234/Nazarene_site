const contactForm = document.querySelector('.contact-form');

if (contactForm) {
    const status = document.getElementById('contact-form-status');
    const submitButton = contactForm.querySelector('button[type="submit"]');

    contactForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        status.hidden = true;
        submitButton.disabled = true;
        submitButton.textContent = 'Sending...';

        try {
            const response = await fetch(contactForm.action, {
                method: 'POST',
                body: new FormData(contactForm),
                headers: {
                    Accept: 'application/json'
                }
            });

            if (!response.ok) {
                throw new Error(`Form submission failed with status ${response.status}`);
            }

            contactForm.reset();
            status.textContent = 'Your message was sent successfully.';
        } catch (error) {
            console.error('Unable to send contact form:', error);
            status.textContent = 'Your message could not be sent. Please try again.';
        } finally {
            status.hidden = false;
            submitButton.disabled = false;
            submitButton.textContent = 'Send Message';
        }
    });
}
