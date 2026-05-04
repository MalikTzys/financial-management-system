<?php if (!is_logged_in()): ?>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<?php else: ?>
    </div>
    <footer class="main-footer">
        <strong>Copyright &copy; <?php echo date('Y'); ?> <a href="#"><?php echo APP_NAME; ?></a>.</strong>
        All rights reserved.
        <div class="float-right d-none d-sm-inline-block">
            <b>Version</b> <?php echo APP_VERSION; ?>
        </div>
    </footer>

    <aside class="control-sidebar control-sidebar-dark">
    </aside>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="<?php echo (strpos($_SERVER['PHP_SELF'], '/reports/') !== false) ? '../' : ''; ?>assets/js/custom.js"></script>

<?php if (basename($_SERVER['PHP_SELF']) == 'dashboard.php'): ?>
<!-- Dashboard Charts Script -->
<script>
// Helper function to format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(amount);
}

// Income Chart
const incomeCtx = document.getElementById('incomeChart');
if (incomeCtx) {
    const incomeLabels = <?php echo json_encode(array_keys($income_by_category ?? [])); ?>;
    const incomeData = <?php echo json_encode(array_values($income_by_category ?? [])); ?>;
    
    const incomeChart = new Chart(incomeCtx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: incomeLabels.length > 0 ? incomeLabels : ['No Data'],
            datasets: [{
                data: incomeData.length > 0 ? incomeData : [1],
                backgroundColor: [
                    '#28a745', '#17a2b8', '#ffc107', '#6f42c1', 
                    '#fd7e14', '#20c997', '#6c757d', '#e83e8c'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    display: incomeLabels.length > 0
                }
            }
        }
    });
}

// Expense Chart
const expenseCtx = document.getElementById('expenseChart');
if (expenseCtx) {
    const expenseLabels = <?php echo json_encode(array_keys($expense_by_category ?? [])); ?>;
    const expenseData = <?php echo json_encode(array_values($expense_by_category ?? [])); ?>;
    
    const expenseChart = new Chart(expenseCtx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: expenseLabels.length > 0 ? expenseLabels : ['No Data'],
            datasets: [{
                data: expenseData.length > 0 ? expenseData : [1],
                backgroundColor: [
                    '#dc3545', '#fd7e14', '#ffc107', '#28a745', 
                    '#17a2b8', '#6f42c1', '#e83e8c', '#6c757d'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    display: expenseLabels.length > 0
                }
            }
        }
    });
}

// Monthly Trend Chart
const trendCtx = document.getElementById('trendChart');
if (trendCtx) {
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    
    const trendChart = new Chart(trendCtx.getContext('2d'), {
        type: 'line',
        data: {
            labels: months,
            datasets: [{
                label: 'Income',
                data: Array(12).fill(0), // Will be populated via AJAX
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4
            }, {
                label: 'Expense',
                data: Array(12).fill(0), // Will be populated via AJAX
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return formatCurrency(value);
                        }
                    }
                }
            }
        }
    });

    // Load monthly trend data
    $.ajax({
        url: 'ajax/monthly-trend-data.php',
        method: 'GET',
        dataType: 'json',
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-CSRF-Token', $('meta[name="csrf-token"]').attr('content'));
        },
        success: function(response) {
            if (response.success && response.data) {
                trendChart.data.datasets[0].data = response.data.income || Array(12).fill(0);
                trendChart.data.datasets[1].data = response.data.expense || Array(12).fill(0);
                trendChart.update();
            }
        },
        error: function(xhr, status, error) {
            console.error('Failed to load monthly trend data:', error);
        }
    });
}

</script>
<?php endif; ?>

<?php if (basename($_SERVER['PHP_SELF']) == 'income.php'): ?>
<script>
$('#category').on('change', function() {
    if ($(this).val() === 'other') {
        $('#newCategoryGroup').show();
        $('#newCategory').prop('required', true);
    } else {
        $('#newCategoryGroup').hide();
        $('#newCategory').prop('required', false);
    }
});

$('#incomeForm').on('submit', function(e) {
    if (!validateForm('incomeForm')) {
        e.preventDefault();
        return false;
    }
    
    if ($('#category').val() === 'other') {
        const newCategory = $('#newCategory').val().trim();
        if (!newCategory) {
            showNotification('Masukkan nama kategori baru terlebih dahulu.', 'warning');
            e.preventDefault();
            return false;
        }
        $('#category').val(newCategory);
    }
    
    showLoading();
});

$('#searchInput').on('input', function() {
    filterTable();
});

$('#monthFilter').on('change', function() {
    filterTable();
});

$('#categoryFilter').on('change', function() {
    filterTable();
});

function filterTable() {
    const searchTerm = $('#searchInput').val().toLowerCase();
    const monthFilter = $('#monthFilter').val();
    const categoryFilter = $('#categoryFilter').val();
    
    $('#incomeTable tbody tr').each(function() {
        const row = $(this);
        const date = row.data('date');
        const category = row.data('category');
        const text = row.text().toLowerCase();
        
        let show = true;
        
        if (searchTerm && !text.includes(searchTerm)) {
            show = false;
        }

        if (monthFilter && !date.startsWith(monthFilter)) {
            show = false;
        }

        if (categoryFilter && category !== categoryFilter) {
            show = false;
        }
        
        row.toggle(show);
    });
}

function resetFilters() {
    $('#searchInput').val('');
    $('#monthFilter').val('');
    $('#categoryFilter').val('');
    filterTable();
}

function editIncome(id) {
    const row = $(`#incomeTable tbody tr[data-id="${id}"]`);
    if (!row.length) {
        showNotification('Data pemasukan tidak ditemukan.', 'warning');
        return;
    }
    $('#edit_income_id').val(id);
    $('#edit_income_date').val(row.data('date'));
    $('#edit_income_amount').val(row.data('amount'));
    $('#edit_income_category').val(row.data('category'));
    $('#edit_income_description').val(row.data('description'));
    $('#editIncomeModal').modal('show');
}

function deleteIncome(id) {
    if (confirmDelete('Are you sure you want to delete this income record?')) {
        window.location.href = 'income.php?delete=' + id;
    }
}

function exportIncome() {
    window.location.href = 'income.php?export=csv';
}

function printIncome() {
    window.print();
}
</script>
<?php endif; ?>

<?php if (basename($_SERVER['PHP_SELF']) == 'expense.php'): ?>
<script>
$('#category').on('change', function() {
    if ($(this).val() === 'other') {
        $('#newCategoryGroup').show();
        $('#newCategory').prop('required', true);
    } else {
        $('#newCategoryGroup').hide();
        $('#newCategory').prop('required', false);
    }
});

$('#expenseForm').on('submit', function(e) {
    if (!validateForm('expenseForm')) {
        e.preventDefault();
        return false;
    }
    
    if ($('#category').val() === 'other') {
        const newCategory = $('#newCategory').val().trim();
        if (!newCategory) {
            showNotification('Masukkan nama kategori baru terlebih dahulu.', 'warning');
            e.preventDefault();
            return false;
        }
        $('#category').val(newCategory);
    }
    
    if ($('#budgetAlert').is(':checked')) {
        if (!confirm('This is marked as a budget-critical expense. Are you sure?')) {
            e.preventDefault();
            return false;
        }
    }
    
    showLoading();
});

$('#searchInput').on('input', filterTable);
$('#monthFilter').on('change', filterTable);
$('#categoryFilter').on('change', filterTable);
$('#amountFilter').on('change', filterTable);

function filterTable() {
    const searchTerm = $('#searchInput').val().toLowerCase();
    const monthFilter = $('#monthFilter').val();
    const categoryFilter = $('#categoryFilter').val();
    const amountFilter = $('#amountFilter').val();
    
    let totalAmount = 0;
    let visibleRows = 0;
    
    $('#expenseTable tbody tr').each(function() {
        const row = $(this);
        const date = row.data('date');
        const category = row.data('category');
        const amount = parseFloat(row.data('amount'));
        const text = row.text().toLowerCase();
        
        let show = true;
        
        if (searchTerm && !text.includes(searchTerm)) {
            show = false;
        }

        if (monthFilter && !date.startsWith(monthFilter)) {
            show = false;
        }

        if (categoryFilter && category !== categoryFilter) {
            show = false;
        }

        if (amountFilter) {
            if (amountFilter === '0-100000' && amount >= 100000) show = false;
            if (amountFilter === '100000-500000' && (amount < 100000 || amount >= 500000)) show = false;
            if (amountFilter === '500000-1000000' && (amount < 500000 || amount >= 1000000)) show = false;
            if (amountFilter === '1000000+' && amount < 1000000) show = false;
        }
        
        row.toggle(show);
        
        if (show) {
            totalAmount += amount;
            visibleRows++;
        }
    });
    
    $('#totalExpense').text(formatCurrency(totalAmount));

    if (visibleRows === 0) {
        if (!$('#noResultsMessage').length) {
            $('#expenseTable tbody').append('<tr id="noResultsMessage"><td colspan="5" class="text-center">No expense records found matching your criteria</td></tr>');
        }
    } else {
        $('#noResultsMessage').remove();
    }
}

function resetFilters() {
    $('#searchInput').val('');
    $('#monthFilter').val('');
    $('#categoryFilter').val('');
    $('#amountFilter').val('');
    filterTable();
}

function editExpense(id) {
    const row = $(`#expenseTable tbody tr[data-id="${id}"]`);
    if (!row.length) {
        showNotification('Data pengeluaran tidak ditemukan.', 'warning');
        return;
    }
    $('#edit_expense_id').val(id);
    $('#edit_expense_date').val(row.data('date'));
    $('#edit_expense_amount').val(row.data('amount'));
    $('#edit_expense_category').val(row.data('category'));
    $('#edit_expense_description').val(row.data('description'));
    $('#editExpenseModal').modal('show');
}

function deleteExpense(id) {
    if (confirmDelete('Are you sure you want to delete this expense record?')) {
        window.location.href = 'expense.php?delete=' + id;
    }
}

function duplicateExpense(id) {
    showNotification('Fitur duplikasi pengeluaran akan segera tersedia.', 'info');
}

function exportExpense() {
    window.location.href = 'expense.php?export=csv';
}

function printExpense() {
    window.print();
}

function showBudgetAnalysis() {
    showNotification('Fitur analisis anggaran akan segera tersedia.', 'info');
}
</script>
<?php endif; ?>

<?php if (basename($_SERVER['PHP_SELF']) == 'statistics.php'): ?>
<!-- Statistics Page Script -->
<script>
// Helper function to format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(amount);
}

// Period selection handler
function updatePeriodOptions() {
    const periodSelect = document.getElementById('period');
    const period = periodSelect ? periodSelect.value : 'year';
    
    const monthGroup = document.getElementById('monthGroup');
    const quarterGroup = document.getElementById('quarterGroup');
    const semesterGroup = document.getElementById('semesterGroup');
    
    // Hide all groups first
    if (monthGroup) monthGroup.style.display = 'none';
    if (quarterGroup) quarterGroup.style.display = 'none';
    if (semesterGroup) semesterGroup.style.display = 'none';
    
    // Show relevant group
    if (period === 'month') {
        if (monthGroup) monthGroup.style.display = 'block';
    } else if (period === 'quarter') {
        if (quarterGroup) quarterGroup.style.display = 'block';
    } else if (period === 'semester') {
        if (semesterGroup) semesterGroup.style.display = 'block';
    }
}

// Initialize all charts with vanilla JavaScript
document.addEventListener('DOMContentLoaded', function() {
    updatePeriodOptions();
    
    // Add event listener for period selection
    const periodSelect = document.getElementById('period');
    if (periodSelect) {
        periodSelect.addEventListener('change', updatePeriodOptions);
    }
    
    // Initialize Comparison Chart (Income vs Expense)
    const comparisonCtx = document.getElementById('comparisonChart');
    if (comparisonCtx) {
        try {
            const totalIncome = <?php echo isset($summary['total_income']) ? $summary['total_income'] : 0; ?>;
            const totalExpense = <?php echo isset($summary['total_expense']) ? $summary['total_expense'] : 0; ?>;
            
            new Chart(comparisonCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['Income', 'Expense'],
                    datasets: [{
                        label: 'Amount',
                        data: [totalIncome, totalExpense],
                        backgroundColor: ['#28a745', '#dc3545'],
                        borderColor: ['#1e7e34', '#c82333'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + formatCurrency(context.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return formatCurrency(value);
                                }
                            }
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error initializing comparison chart:', error);
        }
    }
    
    // Initialize Distribution Chart (Pie/Doughnut)
    const distributionCtx = document.getElementById('distributionChart');
    if (distributionCtx) {
        try {
            const totalIncome = <?php echo isset($summary['total_income']) ? $summary['total_income'] : 0; ?>;
            const totalExpense = <?php echo isset($summary['total_expense']) ? $summary['total_expense'] : 0; ?>;
            
            new Chart(distributionCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Income', 'Expense'],
                    datasets: [{
                        data: [totalIncome, totalExpense],
                        backgroundColor: ['#28a745', '#dc3545'],
                        borderColor: ['#fff', '#fff'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = formatCurrency(context.raw);
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((context.raw / total) * 100).toFixed(1) : 0;
                                    return label + ': ' + value + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error initializing distribution chart:', error);
        }
    }
    
    // Initialize Trend Chart
    const trendCtx = document.getElementById('trendChart');
    if (trendCtx) {
        try {
            const comparisonData = <?php echo json_encode($comparison_data ?? []); ?>;
            const labels = Object.values(comparisonData).map(item => item.label);
            const incomeData = Object.values(comparisonData).map(item => item.income);
            const expenseData = Object.values(comparisonData).map(item => item.expense);
            
            new Chart(trendCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Income',
                        data: incomeData,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Expense',
                        data: expenseData,
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            position: 'top'
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + formatCurrency(context.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return formatCurrency(value);
                                }
                            }
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error initializing trend chart:', error);
        }
    }
    
    // Initialize Top Income Categories Chart
    const topIncomeCtx = document.getElementById('topIncomeChart');
    if (topIncomeCtx) {
        try {
            const topIncomeCategories = <?php echo json_encode($top_income_categories ?? []); ?>;
            const incomeLabels = Object.keys(topIncomeCategories);
            const incomeData = Object.values(topIncomeCategories).map(item => item.total || 0);
            
            // Validate data
            const validLabels = incomeLabels.filter((label, index) => label && incomeData[index] > 0);
            const validData = incomeData.filter((value, index) => incomeLabels[index] && value > 0);
            
            new Chart(topIncomeCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: validLabels.length > 0 ? validLabels : ['No Data'],
                    datasets: [{
                        label: 'Total Income',
                        data: validData.length > 0 ? validData : [0],
                        backgroundColor: '#28a745',
                        borderColor: '#1e7e34',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return formatCurrency(context.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            max: validData.length > 0 ? Math.max(...validData) * 1.1 : 100,
                            ticks: {
                                callback: function(value) {
                                    return formatCurrency(value);
                                }
                            }
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error initializing top income chart:', error);
            // Display error message
            const ctx = topIncomeCtx.getContext('2d');
            ctx.font = '14px Arial';
            ctx.fillStyle = '#666';
            ctx.textAlign = 'center';
            ctx.fillText('Chart data unavailable', topIncomeCtx.width / 2, topIncomeCtx.height / 2);
        }
    }
    
    // Initialize Top Expense Categories Chart
    const topExpenseCtx = document.getElementById('topExpenseChart');
    if (topExpenseCtx) {
        try {
            const topExpenseCategories = <?php echo json_encode($top_expense_categories ?? []); ?>;
            const expenseLabels = Object.keys(topExpenseCategories);
            const expenseData = Object.values(topExpenseCategories).map(item => item.total || 0);
            
            // Validate data
            const validLabels = expenseLabels.filter((label, index) => label && expenseData[index] > 0);
            const validData = expenseData.filter((value, index) => expenseLabels[index] && value > 0);
            
            new Chart(topExpenseCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: validLabels.length > 0 ? validLabels : ['No Data'],
                    datasets: [{
                        label: 'Total Expense',
                        data: validData.length > 0 ? validData : [0],
                        backgroundColor: '#dc3545',
                        borderColor: '#c82333',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return formatCurrency(context.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            max: validData.length > 0 ? Math.max(...validData) * 1.1 : 100,
                            ticks: {
                                callback: function(value) {
                                    return formatCurrency(value);
                                }
                            }
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error initializing top expense chart:', error);
            // Display error message
            const ctx = topExpenseCtx.getContext('2d');
            ctx.font = '14px Arial';
            ctx.fillStyle = '#666';
            ctx.textAlign = 'center';
            ctx.fillText('Chart data unavailable', topExpenseCtx.width / 2, topExpenseCtx.height / 2);
        }
    }
});

// Export statistics function
function exportStatistics() {
    const periodSelect = document.getElementById('period');
    const yearSelect = document.getElementById('year');
    const monthSelect = document.getElementById('month');
    const quarterSelect = document.getElementById('quarter');
    const semesterSelect = document.getElementById('semester');
    
    const period = periodSelect ? periodSelect.value : 'year';
    const year = yearSelect ? yearSelect.value : new Date().getFullYear();
    
    let url = 'statistics.php?export=csv&period=' + encodeURIComponent(period) + '&year=' + encodeURIComponent(year);
    
    if (period === 'month' && monthSelect) {
        url += '&month=' + encodeURIComponent(monthSelect.value);
    } else if (period === 'quarter' && quarterSelect) {
        url += '&quarter=' + encodeURIComponent(quarterSelect.value);
    } else if (period === 'semester' && semesterSelect) {
        url += '&semester=' + encodeURIComponent(semesterSelect.value);
    }
    
    window.location.href = url;
}

</script>
<?php endif; ?>

<?php if (basename($_SERVER['PHP_SELF']) == 'settings.php'): ?>
<!-- Settings Page Script -->
<script>
// Show/hide password
$('#show_password').on('change', function() {
    const type = $(this).is(':checked') ? 'text' : 'password';
    $('#current_password, #new_password, #confirm_password').attr('type', type);
});

// File input label update
$('#profile_photo').on('change', function() {
    const fileName = $(this).val().split('\\').pop();
    $(this).next('.custom-file-label').html(fileName);
});

// Form validations
$('#profileForm').on('submit', function(e) {
    if (!validateForm('profileForm')) {
        e.preventDefault();
        return false;
    }
    showLoading();
});

$('#photoForm').on('submit', function(e) {
    const fileInput = $('#profile_photo')[0];
    if (!fileInput.files || !fileInput.files[0]) {
        alert('Please select a photo to upload');
        e.preventDefault();
        return false;
    }
    showLoading();
});

$('#passwordForm').on('submit', function(e) {
    const newPassword = $('#new_password').val();
    const confirmPassword = $('#confirm_password').val();
    
    if (newPassword !== confirmPassword) {
        alert('New passwords do not match');
        e.preventDefault();
        return false;
    }
    
    if (newPassword.length < 8) {
        alert('Password must be at least 8 characters');
        e.preventDefault();
        return false;
    }
    
    if (!/(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}/.test(newPassword)) {
        alert('Password must contain uppercase, lowercase, and number');
        e.preventDefault();
        return false;
    }
    
    showLoading();
});

$('#settingsForm').on('submit', function(e) {
    showLoading();
});

function removePhoto() {
    if (confirm('Are you sure you want to remove your profile photo?')) {
        // Implement remove photo functionality
        alert('Remove photo functionality will be implemented');
    }
}
</script>
<?php endif; ?>

<?php if (basename($_SERVER['PHP_SELF']) == 'members.php'): ?>
<!-- Members Page Script -->
<script>
// Search and filter functionality
$('#searchInput').on('input', filterTable);
$('#statusFilter').on('change', filterTable);
$('#paymentFilter').on('change', filterTable);

function filterTable() {
    const searchTerm = $('#searchInput').val().toLowerCase();
    const statusFilter = $('#statusFilter').val();
    const paymentFilter = $('#paymentFilter').val();
    
    $('#membersTable tbody tr').each(function() {
        const row = $(this);
        const name = row.data('name');
        const email = row.data('email');
        const status = row.data('status');
        const payment = row.data('payment');
        
        let show = true;
        
        // Search filter
        if (searchTerm && !name.toLowerCase().includes(searchTerm) && !email.toLowerCase().includes(searchTerm)) {
            show = false;
        }
        
        // Status filter
        if (statusFilter && status !== statusFilter) {
            show = false;
        }
        
        // Payment filter
        if (paymentFilter && payment !== paymentFilter) {
            show = false;
        }
        
        row.toggle(show);
    });
}

function resetFilters() {
    $('#searchInput').val('');
    $('#statusFilter').val('');
    $('#paymentFilter').val('');
    filterTable();
}

function recordPayment(memberId, memberName) {
    $('#payment_member_id').val(memberId);
    $('#payment_member_name').val(memberName);
    $('#paymentModal').modal('show');
}

function viewMember(memberId) {
    alert('View member details: ' + memberId);
}

function editMember(memberId) {
    alert('Edit member: ' + memberId);
}

function deleteMember(memberId, memberName) {
    if (confirmDelete('Are you sure you want to delete member ' + memberName + '?')) {
        window.location.href = 'members.php?delete=' + memberId;
    }
}

function exportMembers() {
    alert('Export functionality will be implemented');
}

function printMembers() {
    window.print();
}

function sendPaymentReminders() {
    if (confirm('Send payment reminders to all unpaid members?')) {
        alert('Payment reminders will be sent');
    }
}

// Form validation
$('#addMemberForm, #paymentForm').on('submit', function(e) {
    if (!validateForm($(this).attr('id'))) {
        e.preventDefault();
        return false;
    }
    showLoading();
});
</script>
<?php endif; ?>

<?php if (basename($_SERVER['PHP_SELF']) == 'monthly.php'): ?>
<!-- Monthly Report Script -->
<script>
// Helper function to format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(amount);
}

// Daily Trend Chart
$(document).ready(function() {
    const dailyCtx = document.getElementById('dailyTrendChart');
    if (dailyCtx) {
        try {
            const dailyLabels = <?php echo isset($days_in_month) ? json_encode(range(1, $days_in_month)) : 'json_encode(range(1, 30))'; ?>;
            const dailyIncome = <?php echo isset($daily_income) ? json_encode(array_values($daily_income)) : '[]'; ?>;
            const dailyExpense = <?php echo isset($daily_expense) ? json_encode(array_values($daily_expense)) : '[]'; ?>;

            // Ensure data is valid
            const validLabels = Array.isArray(dailyLabels) ? dailyLabels : [];
            const validIncome = Array.isArray(dailyIncome) ? dailyIncome : [];
            const validExpense = Array.isArray(dailyExpense) ? dailyExpense : [];

            new Chart(dailyCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: validLabels.map(day => 'Day ' + day),
                    datasets: [{
                        label: 'Income',
                        data: validIncome,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Expense',
                        data: validExpense,
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        intersect: false,
                        mode: 'index'
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: '#ddd',
                            borderWidth: 1,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + formatCurrency(context.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            display: true,
                            title: {
                                display: true,
                                text: 'Day of Month'
                            },
                            grid: {
                                display: false
                            }
                        },
                        y: {
                            beginAtZero: true,
                            display: true,
                            title: {
                                display: true,
                                text: 'Amount (IDR)'
                            },
                            ticks: {
                                callback: function(value) {
                                    return formatCurrency(value);
                                }
                            },
                            grid: {
                                borderDash: [2, 2]
                            }
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error initializing daily trend chart:', error);
            // Display error message on chart
            dailyCtx.getContext('2d').font = '16px Arial';
            dailyCtx.getContext('2d').fillStyle = '#666';
            dailyCtx.getContext('2d').textAlign = 'center';
            dailyCtx.getContext('2d').fillText('Chart data unavailable', dailyCtx.width / 2, dailyCtx.height / 2);
        }
    }
});

// Pie Chart
$(document).ready(function() {
    const pieCtx = document.getElementById('pieChart');
    if (pieCtx) {
        try {
            const totalIncome = <?php echo isset($summary['total_income']) ? $summary['total_income'] : 0; ?>;
            const totalExpense = <?php echo isset($summary['total_expense']) ? $summary['total_expense'] : 0; ?>;
            
            // Ensure data is valid numbers
            const validIncome = parseFloat(totalIncome) || 0;
            const validExpense = parseFloat(totalExpense) || 0;
            
            new Chart(pieCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Income', 'Expense'],
                    datasets: [{
                        data: [validIncome, validExpense],
                        backgroundColor: ['#28a745', '#dc3545'],
                        borderColor: ['#fff', '#fff'],
                        borderWidth: 2,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            top: 20,
                            bottom: 20,
                            left: 10,
                            right: 10
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                boxWidth: 12,
                                font: {
                                    size: 12,
                                    weight: '500'
                                },
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleColor: '#fff',
                            bodyColor: '#fff',
                            borderColor: '#ddd',
                            borderWidth: 1,
                            displayColors: true,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = formatCurrency(context.raw);
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((context.raw / total) * 100).toFixed(1) : 0;
                                    return label + ': ' + value + ' (' + percentage + '%)';
                                }
                            }
                        }
                    },
                    animation: {
                        animateScale: true,
                        animateRotate: true
                    }
                }
            });
        } catch (error) {
            console.error('Error initializing pie chart:', error);
            // Display error message on chart
            pieCtx.getContext('2d').font = '16px Arial';
            pieCtx.getContext('2d').fillStyle = '#666';
            pieCtx.getContext('2d').textAlign = 'center';
            pieCtx.getContext('2d').fillText('Chart data unavailable', pieCtx.width / 2, pieCtx.height / 2);
        }
    }
});

function exportReport() {
    alert('PDF export functionality will be implemented');
}

function printReport() {
    window.print();
}

</script>
<?php endif; ?>

<?php if (basename($_SERVER['PHP_SELF']) == 'semester.php'): ?>
<!-- Semester Report Script -->
<script>
// Helper function to format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(amount);
}

// Monthly Trend Chart
$(document).ready(function() {
    const monthlyCtx = document.getElementById('monthlyTrendChart');
    if (monthlyCtx) {
        const monthNames = <?php echo json_encode(array_column($monthly_data, 'month_name')); ?>;
        const monthlyIncome = <?php echo json_encode(array_column($monthly_data, 'income')); ?>;
        const monthlyExpense = <?php echo json_encode(array_column($monthly_data, 'expense')); ?>;
        const monthlyBalance = <?php echo json_encode(array_column($monthly_data, 'balance')); ?>;

        new Chart(monthlyCtx.getContext('2d'), {
        type: 'line',
        data: {
            labels: monthNames,
            datasets: [{
                label: 'Income',
                data: monthlyIncome,
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4
            }, {
                label: 'Expense',
                data: monthlyExpense,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                tension: 0.4
            }, {
                label: 'Balance',
                data: monthlyBalance,
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return formatCurrency(value);
                        }
                    }
                }
            }
        }
    });
}
});

// Comparison Chart
$(document).ready(function() {
    const comparisonCtx = document.getElementById('comparisonChart');
    if (comparisonCtx) {
        new Chart(comparisonCtx.getContext('2d'), {
        type: 'bar',
        data: {
            labels: ['Income', 'Expense'],
            datasets: [{
                label: 'Total',
                data: [<?php echo $summary['total_income']; ?>, <?php echo $summary['total_expense']; ?>],
                backgroundColor: ['#28a745', '#dc3545']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return formatCurrency(value);
                        }
                    }
                }
            }
        }
    });
}
});

// Income Category Chart
$(document).ready(function() {
    const incomeCategoryCtx = document.getElementById('incomeCategoryChart');
    if (incomeCategoryCtx) {
        const incomeLabels = <?php echo json_encode(array_keys($income_by_category)); ?>;
        const incomeData = <?php echo json_encode(array_column($income_by_category, 'total')); ?>;

        new Chart(incomeCategoryCtx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: incomeLabels.length > 0 ? incomeLabels : ['No Data'],
            datasets: [{
                data: incomeData.length > 0 ? incomeData : [1],
                backgroundColor: [
                    '#28a745', '#17a2b8', '#ffc107', '#6f42c1', 
                    '#fd7e14', '#20c997', '#6c757d', '#e83e8c'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
}
});

// Expense Category Chart
$(document).ready(function() {
    const expenseCategoryCtx = document.getElementById('expenseCategoryChart');
    if (expenseCategoryCtx) {
        const expenseLabels = <?php echo json_encode(array_keys($expense_by_category)); ?>;
        const expenseData = <?php echo json_encode(array_column($expense_by_category, 'total')); ?>;

        new Chart(expenseCategoryCtx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: expenseLabels.length > 0 ? expenseLabels : ['No Data'],
            datasets: [{
                data: expenseData.length > 0 ? expenseData : [1],
                backgroundColor: [
                    '#dc3545', '#fd7e14', '#ffc107', '#28a745', 
                    '#17a2b8', '#6f42c1', '#e83e8c', '#6c757d'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
}
});

function exportReport() {
    alert('PDF export functionality will be implemented');
}

function printReport() {
    window.print();
}

</script>
<?php endif; ?>

<div id="loading-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>
</div>

<div id="notifications-container" style="position: fixed; top: 20px; right: 20px; z-index: 9998; max-width: 400px;"></div>
<div class="modal fade" id="transactionDetailModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detail Aktivitas Finansial</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="transaction-detail-content"></div>
        </div>
    </div>
</div>

<?php endif; ?>

</body>
</html>
