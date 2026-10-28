// Load events data from JSON and provide utilities for rendering
let eventsData = [];

/**
 * Load events from the JSON data file
 */
async function loadEvents() {
    try {
        const response = await fetch('data/events.json');
        if (!response.ok) {
            throw new Error(`Failed to load events: ${response.status}`);
        }
        eventsData = await response.json();
        return eventsData;
    } catch (error) {
        console.error('Error loading events:', error);
        return [];
    }
}

/**
 * Filter events by type (e.g., "mission", "youth", "worship")
 * @param {string} type - Event type to filter by
 * @returns {array} Filtered events
 */
function getEventsByType(type) {
    return eventsData.filter(event => event.type === type);
}

function parseEventDate(dateString) {
    const match = String(dateString || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
    return match
        ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
        : new Date(dateString);
}

/**
 * Get all events sorted by date (newest first)
 * @returns {array} Sorted events
 */
function getAllEventsSorted() {
    return eventsData.sort((a, b) => parseEventDate(b.date) - parseEventDate(a.date));
}

/**
 * Format date string to readable format
 * @param {string} dateString - ISO date string (YYYY-MM-DD)
 * @returns {string} Formatted date
 */
function formatEventDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    const date = parseEventDate(dateString);
    return Number.isNaN(date.getTime()) ? 'Date to be announced' : date.toLocaleDateString('en-US', options);
}

/**
 * Get month and year from date string for display
 * @param {string} dateString - ISO date string
 * @returns {string} Month and year (e.g., "May 2026")
 */
function getMonthYear(dateString) {
    const options = { year: 'numeric', month: 'short' };
    const date = parseEventDate(dateString);
    return Number.isNaN(date.getTime()) ? 'Date to be announced' : date.toLocaleDateString('en-US', options);
}

function escapeEventHtml(value) {
    const element = document.createElement('span');
    element.textContent = value == null ? '' : String(value);
    return element.innerHTML;
}

function getEventFallbackImage(type, id) {
    const eventType = String(type || '').toLowerCase();
    if (eventType.includes('mission') || eventType.includes('outreach')) {
        const missionImages = [
            'images/home-hero-4.jpg',
            'images/home-hero-3.jpg',
            'images/home-hero-2.jpg',
            'images/home-hero-1.jpg'
        ];
        const imageIndex = Math.abs(Number(id) || 0) % missionImages.length;
        return missionImages[imageIndex];
    }
    if (eventType.includes('worship') || eventType.includes('church')) {
        return 'images/home-hero-1.jpg';
    }
    return 'images/home-hero-2.jpg';
}

/**
 * Render a mission event card in the mission highlights format
 * @param {object} event - Event object
 * @returns {string} HTML card markup
 */
function renderMissionEventCard(event) {
    const fallbackImage = getEventFallbackImage(event.type, event.id);
    const image = event.image || fallbackImage;
    return `
        <article class="event-card">
            <span class="date-label">${escapeEventHtml(getMonthYear(event.date))}</span>
            <h3>${escapeEventHtml(event.title)}</h3>
            <p class="event-location">${escapeEventHtml(event.location)}</p>
            <img src="${escapeEventHtml(image)}" alt="${escapeEventHtml(event.title)}" onerror="this.onerror=null;this.src='${fallbackImage}'" />
            <p>${escapeEventHtml(event.description)}</p>
        </article>
    `;
}

/**
 * Render a general event card
 * @param {object} event - Event object
 * @returns {string} HTML card markup
 */
function renderEventCard(event) {
    const fallbackImage = getEventFallbackImage(event.type, event.id);
    const image = event.image || fallbackImage;
    return `
        <article class="card event-card">
            <img class="event-card-image" src="${escapeEventHtml(image)}" alt="${escapeEventHtml(event.title)}" onerror="this.onerror=null;this.src='${fallbackImage}'" />
            <p class="badge">${escapeEventHtml(formatEventDate(event.date))}</p>
            <h3>${escapeEventHtml(event.title)}</h3>
            <p>${escapeEventHtml(event.description)}</p>
            <p><strong>Date:</strong> ${escapeEventHtml(formatEventDate(event.date))}</p>
            <p><strong>Location:</strong> ${escapeEventHtml(event.location || 'Details coming soon')}</p>
            <div class="card-actions"><span class="event-category">${escapeEventHtml(event.type || 'Community')}</span></div>
        </article>
    `;
}

/**
 * Initialize events on page load - expose as global for use in pages
 */
window.initializeEvents = function() {
    return loadEvents();
};
