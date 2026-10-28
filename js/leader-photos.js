document.addEventListener('DOMContentLoaded', async function () {
    const profileGrid = document.querySelector('.leader-grid');
    if (!profileGrid) {
        return;
    }

    try {
        const response = await fetch('api/get-leader-photos.php', {
            headers: { Accept: 'application/json' },
            cache: 'no-store'
        });
        if (!response.ok) {
            throw new Error(`Leader photo request failed: ${response.status}`);
        }

        const result = await response.json();
        if (!result.success || !Array.isArray(result.data)) {
            throw new Error('Leader photos are unavailable.');
        }

        profileGrid.replaceChildren(...result.data.map(profile => {
            const card = document.createElement('article');
            card.className = 'leader-card';

            if (typeof profile.photo === 'string' && /^uploads\/leaders\/[A-Za-z0-9._-]+$/.test(profile.photo)) {
                const image = document.createElement('img');
                image.className = 'leader-photo';
                image.src = profile.photo;
                image.alt = `Photo of ${profile.name}`;
                card.append(image);
            } else {
                const placeholder = document.createElement('div');
                placeholder.className = 'leader-photo-placeholder';
                placeholder.setAttribute('aria-hidden', 'true');
                placeholder.textContent = String(profile.name || '?').trim().split(/\s+/).slice(0, 2).map(name => name[0]).join('').toUpperCase();
                card.append(placeholder);
            }

            const name = document.createElement('h3');
            name.textContent = profile.name;
            const role = document.createElement('p');
            role.className = 'leader-role';
            role.textContent = profile.role;
            card.append(name, role);

            if (profile.description) {
                const description = document.createElement('p');
                description.textContent = profile.description;
                card.append(description);
            }

            return card;
        }));
    } catch (error) {
        console.error('Unable to load leader photos:', error);
    }
});
