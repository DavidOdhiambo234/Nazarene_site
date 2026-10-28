// C:\xampp\htdocs\Nazarene_site\js\load-sermons.js

// Load sermons when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadSermons();
});

async function loadSermons() {
    const container = document.getElementById('sermons-container');
    
    if (!container) {
        console.error('Sermons container not found');
        return;
    }
    
    try {
        // Show loading state
        container.innerHTML = '<div class="loading">📖 Loading sermons...</div>';
        
        // Fetch sermons from API
        const response = await fetch('/api/get-sermons.php?limit=6');
        const result = await response.json();
        
        console.log('Sermons received:', result);
        
        if (result.success && result.data && result.data.length > 0) {
            // Display sermons
            container.innerHTML = result.data.map(sermon => `
                <div class="card sermon-card">
                    ${sermon.video_id ? `
                        <div class="sermon-video">
                            <iframe 
                                src="https://www.youtube.com/embed/${sermon.video_id}" 
                                title="${sermon.title}"
                                frameborder="0" 
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                allowfullscreen>
                            </iframe>
                        </div>
                    ` : `
                        <div class="sermon-no-video">
                            <div class="no-video-icon">🎧</div>
                        </div>
                    `}
                    <div class="card-body">
                        <h3 class="sermon-title">${escapeHtml(sermon.title)}</h3>
                        <div class="sermon-meta">
                            <span class="sermon-speaker">🎤 ${escapeHtml(sermon.speaker)}</span>
                            ${sermon.scripture ? `<span class="sermon-scripture">📖 ${escapeHtml(sermon.scripture)}</span>` : ''}
                        </div>
                        <p class="sermon-description">${escapeHtml(sermon.description || 'Watch this powerful message')}</p>
                        <div class="sermon-footer">
                            <span class="sermon-date">📅 ${new Date(sermon.preached_date).toLocaleDateString()}</span>
                            ${sermon.video_id ? `<a href="https://www.youtube.com/watch?v=${sermon.video_id}" target="_blank" class="btn btn-sm">Watch Sermon →</a>` : ''}
                        </div>
                    </div>
                </div>
            `).join('');
        } else {
            container.innerHTML = `
                <div class="no-sermons">
                    <p>📭 No sermons available yet.</p>
                    <p>Check back soon for uplifting messages!</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading sermons:', error);
        container.innerHTML = `
            <div class="error-message">
                <p>❌ Unable to load sermons.</p>
                <p>Please try again later.</p>
            </div>
        `;
    }
}

// Helper function to prevent XSS attacks
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}