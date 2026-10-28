// C:\xampp\htdocs\Nazarene_site\js\dynamic.js

// API Base URL
const API_BASE = 'http://localhost:8000/api';

// Wait for DOM to load
document.addEventListener('DOMContentLoaded', function() {
    console.log('Dynamic content loader started');
    
    // Load all dynamic content
    loadSettings();
    loadSermons();
    loadEvents();
    setupNewsletter();
});

// Load website settings (hero, theme, colors)
async function loadSettings() {
    try {
        const response = await fetch(`${API_BASE}/get-settings.php`);
        const result = await response.json();
        
        if (result.success && result.data) {
            const settings = result.data;
            
            // Update hero section
            if (settings.hero_background_image) {
                const heroSection = document.querySelector('.hero');
                if (heroSection) {
                    heroSection.style.backgroundImage = `url('${settings.hero_background_image}')`;
                }
            }
            
            // Update hero text
            const heroTitle = document.getElementById('hero-title');
            if (heroTitle) heroTitle.textContent = settings.hero_title || 'Welcome to University Church of the Nazarene';
            
            const heroSubtitle = document.getElementById('hero-subtitle');
            if (heroSubtitle) heroSubtitle.textContent = settings.hero_subtitle || '';
            
            // Update theme section
            const themeYear = document.getElementById('theme-year');
            if (themeYear) themeYear.textContent = settings.theme_year || '2026';
            
            const themeTitle = document.getElementById('theme-title');
            if (themeTitle) themeTitle.textContent = settings.theme_title || 'Living a Fruitful Life';
            
            const themeVerse = document.getElementById('theme-verse');
            if (themeVerse) themeVerse.textContent = settings.theme_verse_text || '';
            
            const themeVerseRef = document.getElementById('theme-verse-ref');
            if (themeVerseRef) themeVerseRef.textContent = settings.theme_verse || 'John 15:5';
        }
    } catch (error) {
        console.error('Error loading settings:', error);
    }
}

// Load sermons from database
async function loadSermons() {
    const container = document.getElementById('sermons-container');
    if (!container) return;
    
    try {
        const response = await fetch(`${API_BASE}/get-sermons.php?limit=3`);
        const result = await response.json();
        
        if (result.success && result.data.length > 0) {
            container.innerHTML = result.data.map(sermon => {
                // Extract YouTube video ID
                let videoId = '';
                if (sermon.video_url) {
                    const match = sermon.video_url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/);
                    videoId = match ? match[1] : '';
                }
                
                return `
                    <article class="card">
                        ${videoId ? `
                            <div class="sermon-video">
                                <iframe 
                                    src="https://www.youtube.com/embed/${videoId}" 
                                    frameborder="0" 
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                    allowfullscreen>
                                </iframe>
                            </div>
                        ` : ''}
                        <div class="card-content">
                            <h3>${escapeHtml(sermon.title)}</h3>
                            <p><strong>🎤 Speaker:</strong> ${escapeHtml(sermon.speaker)}</p>
                            <p><strong>📖 Scripture:</strong> ${escapeHtml(sermon.scripture)}</p>
                            <p>${escapeHtml(sermon.description)}</p>
                            <p><small><strong>📅 Preached:</strong> ${new Date(sermon.preached_date).toLocaleDateString()}</small></p>
                            ${videoId ? `<a href="https://www.youtube.com/watch?v=${videoId}" target="_blank" class="btn btn-primary">Watch Sermon</a>` : ''}
                        </div>
                    </article>
                `;
            }).join('');
        } else {
            container.innerHTML = '<div class="loading">No sermons available yet. Check back soon!</div>';
        }
    } catch (error) {
        console.error('Error loading sermons:', error);
        container.innerHTML = '<div class="loading">Unable to load sermons. Please try again later.</div>';
    }
}

// Load events from database
async function loadEvents() {
    const container = document.getElementById('events-container');
    if (!container) return;
    
    try {
        const response = await fetch(`${API_BASE}/get-events.php?limit=3`);
        const result = await response.json();
        
        if (result.success && result.data.length > 0) {
            container.innerHTML = result.data.map(event => `
                <article class="card event-card" onclick="registerForEvent(${event.id})">
                    <div class="card-content">
                        <div class="event-icon">${getEventIcon(event.event_type)}</div>
                        <p class="badge">${new Date(event.event_date).toLocaleDateString()}</p>
                        <h3>${escapeHtml(event.title)}</h3>
                        <p>${escapeHtml(event.short_description || event.description?.substring(0, 100) || '')}</p>
                        <p><strong>📍 Location:</strong> ${escapeHtml(event.location)}</p>
                        ${event.requires_registration ? 
                            `<button class="btn btn-primary" onclick="event.stopPropagation(); registerForEvent(${event.id})">Register Now</button>` : 
                            '<span class="btn-secondary" style="display:inline-block; padding:10px;">🎉 Free Entry</span>'}
                    </div>
                </article>
            `).join('');
        } else {
            container.innerHTML = '<div class="loading">No upcoming events. Check back soon!</div>';
        }
    } catch (error) {
        console.error('Error loading events:', error);
        container.innerHTML = '<div class="loading">Unable to load events. Please try again later.</div>';
    }
}

// Get emoji for event type
function getEventIcon(type) {
    const icons = {
        'hiking': '🥾',
        'worship': '⛪',
        'bible-study': '📖',
        'fellowship': '🤝',
        'youth': '🎉',
        'outreach': '🤲'
    };
    return icons[type] || '📅';
}

// Register for event
window.registerForEvent = async function(eventId) {
    const fullName = prompt('Enter your full name:');
    if (!fullName) return;
    
    const email = prompt('Enter your email address:');
    if (!email) return;
    
    const phone = prompt('Enter your phone number:');
    if (!phone) return;
    
    try {
        const response = await fetch(`${API_BASE}/register.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                full_name: fullName,
                email: email,
                phone: phone,
                event_id: eventId,
                number_of_guests: 1
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('✓ ' + result.message);
        } else {
            alert('✗ ' + result.message);
        }
    } catch (error) {
        console.error('Registration error:', error);
        alert('✗ Network error. Please try again.');
    }
};

// Setup newsletter subscription
function setupNewsletter() {
    const form = document.getElementById('newsletter-form');
    if (!form) return;
    
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const emailInput = document.getElementById('newsletter-email');
        const email = emailInput ? emailInput.value : '';
        
        if (!email) {
            alert('Please enter your email address');
            return;
        }
        
        const submitBtn = form.querySelector('button');
        const originalText = submitBtn.textContent;
        submitBtn.textContent = 'Subscribing...';
        submitBtn.disabled = true;
        
        try {
            const response = await fetch(`${API_BASE}/newsletter.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: email })
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('✓ ' + result.message);
                form.reset();
            } else {
                alert('✗ ' + result.message);
            }
        } catch (error) {
            console.error('Newsletter error:', error);
            alert('✗ Network error. Please try again.');
        } finally {
            submitBtn.textContent = originalText;
            submitBtn.disabled = false;
        }
    });
}

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}