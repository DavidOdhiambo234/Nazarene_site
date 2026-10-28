// Populate events on the Events page
document.addEventListener('DOMContentLoaded', async function() {
    const featuredContainer = document.getElementById('featured-events-container');
    const allEventsContainer = document.getElementById('all-events-container');
    const homeEventsContainer = document.getElementById('home-events-container');

    try {
        const response = await fetch('api/get-events.php?all=1&limit=100', {
            headers: { Accept: 'application/json' }
        });
        if (!response.ok) {
            throw new Error(`Event request failed: ${response.status}`);
        }

        const result = await response.json();
        if (!result.success || !Array.isArray(result.data)) {
            throw new Error(result.message || 'Event data is unavailable.');
        }

        const allEvents = result.data.map(event => ({
            ...event,
            date: event.event_date || (event.start_date ? event.start_date.slice(0, 10) : ''),
            type: event.event_type || 'community',
            image: event.image_url || ''
        })).filter(event => event.date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const upcomingEvents = allEvents.filter(event => new Date(`${event.date}T00:00:00`) >= today).slice(0, 3);

        if (featuredContainer) {
            featuredContainer.innerHTML = upcomingEvents.length
                ? upcomingEvents.map(renderEventCard).join('')
                : '<p class="events-empty">No upcoming events are scheduled right now.</p>';
        }
        if (allEventsContainer) {
            allEventsContainer.innerHTML = allEvents.length
                ? allEvents.map(renderEventCard).join('')
                : '<p class="events-empty">No published events are available yet.</p>';
        }
        if (homeEventsContainer) {
            homeEventsContainer.innerHTML = upcomingEvents.length
                ? renderEventCard(upcomingEvents[0])
                : '<p class="events-empty">No upcoming events are scheduled right now.</p>';
        }
    } catch (error) {
        console.error('Unable to load events:', error);
        const message = '<p class="events-empty" role="status">Events could not be loaded. Please try again later.</p>';
        if (featuredContainer) featuredContainer.innerHTML = message;
        if (allEventsContainer) allEventsContainer.innerHTML = message;
        if (homeEventsContainer) homeEventsContainer.innerHTML = message;
    }
});
