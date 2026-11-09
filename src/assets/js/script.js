// script.js - Enca Trading POS

// Confirm delete actions
document.addEventListener('DOMContentLoaded', function() {
    // Find all delete links and add confirmation
    const deleteLinks = document.querySelectorAll('a[href*="delete"]');
    deleteLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });

    // Add a "click to print" functionality for receipt page
    const printBtn = document.getElementById('printReceipt');
    if (printBtn) {
        printBtn.addEventListener('click', function() {
            window.print();
        });
    }

    // Auto-hide success/error messages after 5 seconds
    const messages = document.querySelectorAll('.success, .error');
    messages.forEach(msg => {
        setTimeout(() => {
            msg.style.transition = 'opacity 0.5s';
            msg.style.opacity = '0';
            setTimeout(() => msg.remove(), 500);
        }, 5000);
    });
});

// Optional: AJAX function to check low stock without page reload (can be extended)
function checkLowStock() {
    fetch('api/low_stock.php')
        .then(response => response.json())
        .then(data => {
            if (data.count > 0) {
                const alertBox = document.getElementById('stockAlert');
                if (alertBox) {
                    alertBox.innerHTML = `<span style="color:red;">⚠️ ${data.count} items are low on stock!</span>`;
                    alertBox.style.display = 'block';
                }
            }
        })
        .catch(err => console.log('Stock check failed:', err));
}