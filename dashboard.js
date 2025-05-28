// In dashboard.php, before loading dashboard.js, define JS variables:
// <script>
//     const currentUserId = <?php echo json_encode($_SESSION['user_id'] ?? null); ?>;
//     const currentUserType = <?php echo json_encode($user['user_type_id'] ?? null); ?>;
// </script>
// Then in dashboard.js, use currentUserId and currentUserType
document.addEventListener('DOMContentLoaded', function() {
    // Assume currentUserId and currentUserType are defined globally in the PHP page
    // Example: const currentUserId = typeof currentUserId !== 'undefined' ? currentUserId : null;
    //          const currentUserType = typeof currentUserType !== 'undefined' ? currentUserType : null;
    // Or ensure they are always present from the PHP output.

    if (currentUserType === 1) {
        // Load customer data
        loadUpcomingEvents(currentUserId);
    } else if (currentUserType === 2) { // Assuming 2 is vendor type
        // Load vendor data
        loadVendorMetrics(currentUserId);
        // loadRecentMessages(userId); // This function is not defined in the provided JS
    }
    // For other user types or unauthenticated, no dynamic data loading here
});

function loadUpcomingEvents(userId) {
    if (!userId) return;
    fetch(`/api/events/upcoming?user_id=${userId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(events => {
            const container = document.getElementById('upcoming-events');
            if (!container) return;

            if (events.length === 0) {
                container.innerHTML = '<p>No upcoming events found.</p>';
                return;
            }

            let html = '<ul class="event-list">';
            events.forEach(event => {
                html += `
                    <li>
                        <h3><span class="math-inline">\{event\.title\}</h3\>
                        
                        <p>${formatDate(event.event_date)} at ${event.venue_name || 'Location TBD'}</p>
                        <a href="/event.php?id=${event.id}">View Details</a>
                    </li>
                `;
            });
            html += '</ul>';
            
            container.innerHTML = html;
            })
            .catch(error => {
                console.error('Error loading events:', error);
                const container = document.getElementById('upcoming-events');
                if (container) {
                    container.innerHTML = '<p class="error">Could not load events. Please try again later.</p>';
                }
            });
    }

    function loadVendorMetrics(vendorId) {
        if (!vendorId) return;
        fetch(`/api/vendors/metrics?id=${vendorId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                // Ensure elements exist before trying to update them
                const upcomingBookingsEl = document.getElementById('upcoming-bookings');
                if (upcomingBookingsEl) upcomingBookingsEl.textContent = data.upcoming_bookings;
                const totalEarningsEl = document.getElementById('total-earnings');
                if (totalEarningsEl) totalEarningsEl.textContent = `$${data.total_earnings}`;
            })
            .catch(error => {
                console.error('Error loading vendor metrics:', error);
                // Optionally display an error message on the dashboard
            });
    }

    function formatDate(dateString) {
        const options = { year: 'numeric', month: 'long', day: 'numeric' };
        return new Date(dateString).toLocaleDateString(undefined, options);
    }