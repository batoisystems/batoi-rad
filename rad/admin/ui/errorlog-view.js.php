<?php
$urlForAjax = $urlForAjax ?? ($this->runData['route']['rad_admin_url'] ?? '') . '/errorlog/view';
$timezone = $this->runData['entity']['timezone'] ?? ($this->runData['config']['sys']['timezone_default'] ?? 'UTC');
$today = (new DateTime('now', new DateTimeZone($timezone)))->format('Y-m-d');
$currentDate = $currentDate ?? ($this->runData['data']['selected_date'] ?? $today);
?>
<script>
function fetchErrorLogs(date) {
    let formData = new FormData();
    formData.append('date', date);
    formData.append('limit', 25); // Or any other limit you want to set

    fetch('<?php echo $urlForAjax; ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        const tabContent = document.querySelector('#v-pills-tabContent');
        tabContent.innerHTML = ''; // Clear existing content
        let table = '<table class="table table-striped table-hover"><tbody>';
        data.forEach(log => {
            let row = `<tr>
                <td class="small">${log.timestamp}</td>
                <td class="small">${log.error_type}</td>
                <td class="small">${log.file_path}</td>
                <td class="small">${log.message}</td>
            </tr>`;
            table += row;
        });
        table += '</tbody></table>';
        tabContent.innerHTML = table;
    })
    .catch(error => {
        console.error('There has been a problem with your fetch operation:', error);
    });
}

// Add event listeners for the vertical tabs
document.querySelectorAll('.nav-link').forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        // Remove active class from all tabs
        document.querySelectorAll('.nav-link').forEach(tab => {
            tab.classList.remove('active');
        });

        // Add active class to the clicked tab
        this.classList.add('active');

        let date = this.getAttribute('aria-controls').split('-')[2]; // Extract date from the ID
        fetchErrorLogs(date);
    });
});

$('a[data-toggle="pill"]').on('show.bs.tab', function (e) {
    var date = $(e.target).attr('href').split('-').join('/'); // Extract the date in 'YYYY/MM/DD' format
    $.ajax({
        url: '<?php echo $urlForAjax; ?>',
        type: 'POST',
        data: { date: date },
        dataType: 'json',
        success: function(data) {
            // Populate the tab content with the error log data
            $('#v-pills-tabContent').html(data);
        },
        error: function(xhr, status, error) {
            console.error("An error occurred: " + error);
        }
    });
});


// Initially load logs for the current date
fetchErrorLogs('<?php echo $currentDate; ?>');

// Initially set the active tab and load logs for the current date
document.addEventListener("DOMContentLoaded", function() {
    // PHP variable $currentDate needs to be in 'YYYY-MM-DD' format
    let currentDate = '<?php echo $currentDate; ?>';
    // Convert to 'YYYY/MM/DD' format for consistency with tab IDs
    let formattedDate = currentDate.replace(/-/g, '/');
    setActiveTab(formattedDate);
    fetchErrorLogs(formattedDate);
});

document.addEventListener('DOMContentLoaded', function() {
    const purgeSelect = document.getElementById('purge-window');
    const warning = document.getElementById('purge-warning');
    const confirmBtn = document.getElementById('purge-confirm');

    if (!purgeSelect || !warning || !confirmBtn) {
        return;
    }

    purgeSelect.addEventListener('change', function() {
        const label = this.options[this.selectedIndex]?.text || '';
        if (label) {
            warning.textContent = 'This will delete logs ' + label.toLowerCase() + '. This action cannot be undone.';
            confirmBtn.disabled = false;
        } else {
            warning.textContent = 'Select a window to see what will be removed.';
            confirmBtn.disabled = true;
        }
    });
});
</script>
