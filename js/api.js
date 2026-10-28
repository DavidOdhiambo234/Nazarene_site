// C:\xampp\htdocs\Nazarene_site\js\api.js
console.log('API.js loaded');

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded');
    testAPIConnection();
    setupNewsletterForm();
    loadEvents();
    loadSermons();
});

async function testAPIConnection() {
    try {
        const response = await fetch('/api/ping.php');
        const data = await response.json();
        console.log('API Test:', data);
        if (data.success) {
            console.log('✓ API is working');
        }
    } catch (error) {
        console.error('✗ API failed:', error);
    }
}

async function loadEvents() {
    try {
        const response = await fetch('/api/events.php');
        const data = await response.json();
        if (data.success && data.data) {
            console.log('Events loaded:', data.data.length);
        }
    } catch (error) {
        console.error('Error loading events:', error);
    }
}

async function loadSermons() {
    try {
        const response = await fetch('/api/sermons.php');
        const data = await response.json();
        if (data.success && data.data) {
            console.log('Sermons loaded:', data.data.length);
        }
    } catch (error) {
        console.error('Error loading sermons:', error);
    }
}

function setupNewsletterForm() {
    const newsletterForm = document.getElementById('newsletter-form');
    if (newsletterForm) {
        console.log('Newsletter form found');
        newsletterForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const email = document.getElementById('newsletter-email')?.value;
            if (!email) {
                alert('Please enter your email');
                return;
            }
            
            const btn = newsletterForm.querySelector('button');
            const originalText = btn.textContent;
            btn.textContent = 'Subscribing...';
            btn.disabled = true;
            
            try {
                const response = await fetch('/api/newsletter.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: email })
                });
                const result = await response.json();
                alert(result.message);
                if (result.success) newsletterForm.reset();
            } catch (error) {
                alert('Error: ' + error.message);
            } finally {
                btn.textContent = originalText;
                btn.disabled = false;
            }
        });
    } else {
        console.log('Newsletter form NOT found');
    }
}

window.registerForEvent = async function(eventId) {
    const fullName = prompt('Enter your full name:');
    if (!fullName) return;
    const email = prompt('Enter your email:');
    if (!email) return;
    const phone = prompt('Enter your phone number:');
    if (!phone) return;
    
    try {
        const response = await fetch('/api/register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                full_name: fullName,
                email: email,
                phone: phone,
                event_id: eventId
            })
        });
        const result = await response.json();
        alert(result.message);
    } catch (error) {
        alert('Error: ' + error.message);
    }
};