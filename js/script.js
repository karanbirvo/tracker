        // Current Time Display
        function updateTime() {
            const timeNowElement = document.getElementById('time-now');
            if (timeNowElement) {
                const now = new Date();
                const options = {
                    // timeZone: 'Asia/Kolkata', // Set if you want to force a specific timezone display
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                };
                try {
                    timeNowElement.textContent = now.toLocaleTimeString('en-IN', options); // 'en-IN' for India format
                } catch (e) { // Fallback for older browsers
                    timeNowElement.textContent = now.toLocaleTimeString(undefined, options);
                }
            }
        }
        if (document.getElementById('time-now')) { // Check if the element exists
            updateTime(); // Initial call
            setInterval(updateTime, 1000); // Update every second
        }

        // Mobile Menu Toggle
        const menuToggle = document.getElementById('menu-toggle');
        const navLinks = document.getElementById('nav-links');

        if (menuToggle && navLinks) { // Check if elements exist
            menuToggle.addEventListener('click', () => {
                const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true' || false;
                menuToggle.setAttribute('aria-expanded', !isExpanded);
                navLinks.classList.toggle('active'); // Toggle visibility of nav links
                menuToggle.classList.toggle('active'); // Toggle icon (bars/times)
            });
        }


    // time calculator js
        