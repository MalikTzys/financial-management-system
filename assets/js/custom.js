/**
 * Custom JavaScript for FNC Financial Management System
 * Author: FNC Team
 * Version: 1.0.0
 */

// Global variables
let notificationTimeout;
let chartInstances = {};
const appBasePath = $('body').data('base-path') || '';

// Initialize on document ready
$(document).ready(function() {
    initializeApp();
});

/**
 * Initialize the application
 */
function initializeApp() {
    // Initialize tooltips
    initializeTooltips();
    
    // Initialize popovers
    initializePopovers();
    
    // Initialize date pickers
    initializeDatePickers();
    
    // Initialize file inputs
    initializeFileInputs();
    
    // Initialize custom scrollbars
    initializeCustomScrollbars();
    
    // Initialize auto-refresh functionality
    initializeAutoRefresh();
    
    // Initialize keyboard shortcuts
    initializeKeyboardShortcuts();
    
    // Initialize offline detection
    initializeOfflineDetection();
    
    // Initialize fixed sidebar functionality
    initializeFixedSidebar();
    
    console.log('FNC Financial System initialized successfully');
}

/**
 * Initialize tooltips
 */
function initializeTooltips() {
    $('[data-toggle="tooltip"]').each(function() {
        $(this).tooltip({
            container: 'body',
            trigger: 'hover focus',
            delay: { show: 500, hide: 100 }
        });
    });
}

/**
 * Initialize popovers
 */
function initializePopovers() {
    $('[data-toggle="popover"]').each(function() {
        $(this).popover({
            container: 'body',
            trigger: 'click',
            html: true
        });
    });
    
    // Close popovers when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('[data-toggle="popover"]').length) {
            $('[data-toggle="popover"]').popover('hide');
        }
    });
}

/**
 * Initialize date pickers
 */
function initializeDatePickers() {
    $('input[type="date"]').each(function() {
        const $input = $(this);
        const min = $input.attr('min');
        const max = $input.attr('max');
        
        if (min) $input.attr('min', min);
        if (max) $input.attr('max', max);
        
        // Set default to today if empty
        if (!$input.val() && $input.hasClass('default-today')) {
            $input.val(new Date().toISOString().split('T')[0]);
        }
    });
}

/**
 * Initialize file inputs
 */
function initializeFileInputs() {
    $('.custom-file-input').on('change', function() {
        const fileName = $(this).val().split('\\').pop();
        $(this).next('.custom-file-label').html(fileName || 'Choose file');
    });
}

/**
 * Initialize custom scrollbars
 */
function initializeCustomScrollbars() {
    $('.custom-scrollbar').each(function() {
        $(this).addClass('custom-scrollbar');
    });
}

/**
 * Initialize auto-refresh functionality
 */
function initializeAutoRefresh() {
    // Auto-refresh dashboard every 30 seconds
    if (window.location.pathname.includes('dashboard.php')) {
        setInterval(refreshDashboardData, 30000);
    }
    
    // Auto-refresh for reports disabled to prevent infinite loops
    // if (window.location.pathname.includes('reports/')) {
    //     setInterval(refreshReportData, 60000);
    // }
}

/**
 * Initialize keyboard shortcuts
 */
function initializeKeyboardShortcuts() {
    $(document).on('keydown', function(e) {
        // Ctrl/Cmd + S: Save current form
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            const $form = $('form:visible').first();
            if ($form.length) {
                $form.submit();
            }
        }
        
        // Ctrl/Cmd + N: New transaction (on income/expense pages)
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            e.preventDefault();
            if (window.location.pathname.includes('income.php') || window.location.pathname.includes('expense.php')) {
                resetForm($('form:visible').first().attr('id'));
            }
        }
        
        // Escape: Close modals and sidebar
        if (e.key === 'Escape') {
            // Close modals first
            $('.modal.show').modal('hide');
            
            // Then close sidebar if open (mobile only)
            if ($(window).width() <= 768 && $('body').hasClass('sidebar-open')) {
                closeSidebar();
            }
        }
    });
}

/**
 * Initialize offline detection
 */
function initializeOfflineDetection() {
    window.addEventListener('online', function() {
        showNotification('You are back online', 'success');
    });
    
    window.addEventListener('offline', function() {
        showNotification('You are offline. Some features may not work properly.', 'warning');
    });
}

/**
 * Refresh dashboard data
 */
function refreshDashboardData() {
    $.ajax({
        url: 'ajax/dashboard-data.php',
        method: 'GET',
        dataType: 'json',
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-CSRF-Token', $('meta[name="csrf-token"]').attr('content'));
        },
        success: function(response) {
            if (response.success) {
                updateDashboardUI(response.data);
            }
        },
        error: function() {
            console.warn('Failed to refresh dashboard data');
        }
    });
}

/**
 * Update dashboard UI with fresh data
 */
function updateDashboardUI(data) {
    // Update summary cards
    if (data.summary.yearly) {
        $('#total-income').text(formatCurrency(data.summary.yearly.total_income));
        $('#total-expense').text(formatCurrency(data.summary.yearly.total_expense));
        $('#balance').text(formatCurrency(data.summary.yearly.balance));
    }
    
    // Update recent transactions
    updateRecentTransactions(data.recent_transactions);
    
    // Update charts
    updateCharts(data.charts);
}

/**
 * Update recent transactions table
 */
function updateRecentTransactions(transactions) {
    // Update income table
    if (transactions.income && transactions.income.length > 0) {
        const $incomeTable = $('#incomeTable tbody');
        $incomeTable.empty();
        
        transactions.income.forEach(function(transaction) {
            const row = `
                <tr>
                    <td>${transaction.date}</td>
                    <td><span class="badge badge-success">${transaction.category}</span></td>
                    <td>${transaction.description}</td>
                    <td class="text-success font-weight-bold">+${transaction.amount}</td>
                    <td>
                        <button class="btn btn-sm btn-info" onclick="viewTransaction('income', '${transaction.id}')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
            $incomeTable.append(row);
        });
    }
    
    // Update expense table
    if (transactions.expense && transactions.expense.length > 0) {
        const $expenseTable = $('#expenseTable tbody');
        $expenseTable.empty();
        
        transactions.expense.forEach(function(transaction) {
            const row = `
                <tr>
                    <td>${transaction.date}</td>
                    <td><span class="badge badge-danger">${transaction.category}</span></td>
                    <td>${transaction.description}</td>
                    <td class="text-danger font-weight-bold">-${transaction.amount}</td>
                    <td>
                        <button class="btn btn-sm btn-info" onclick="viewTransaction('expense', '${transaction.id}')">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
            $expenseTable.append(row);
        });
    }
}

/**
 * Update charts with new data
 */
function updateCharts(chartData) {
    // Update income chart
    if (chartData.income_by_category && chartInstances.incomeChart) {
        chartInstances.incomeChart.data.datasets[0].data = Object.values(chartData.income_by_category);
        chartInstances.incomeChart.update();
    }
    
    // Update expense chart
    if (chartData.expense_by_category && chartInstances.expenseChart) {
        chartInstances.expenseChart.data.datasets[0].data = Object.values(chartData.expense_by_category);
        chartInstances.expenseChart.update();
    }
}

/**
 * Refresh report data
 */
function refreshReportData() {
    // Only refresh if we're not already refreshing to prevent infinite loops
    if (window.isRefreshing) {
        return;
    }
    
    window.isRefreshing = true;
    
    // Get current page parameters
    const urlParams = new URLSearchParams(window.location.search);
    const params = {};
    for (const [key, value] of urlParams) {
        params[key] = value;
    }
    
    $.ajax({
        url: window.location.pathname,
        method: 'GET',
        data: params,
        dataType: 'json',
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-CSRF-Token', $('meta[name="csrf-token"]').attr('content'));
        },
        success: function(response) {
            // Don't refresh the entire page, just update data if needed
            console.log('Report data refreshed successfully');
        },
        error: function() {
            console.warn('Failed to refresh report data');
        },
        complete: function() {
            window.isRefreshing = false;
        }
    });
}

/**
 * Format currency with proper formatting
 */
function formatCurrency(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(amount);
}

/**
 * Show notification
 */
function showNotification(message, type = 'info', duration = 5000) {
    // Clear existing timeout
    if (notificationTimeout) {
        clearTimeout(notificationTimeout);
    }
    
    const alertClass = type === 'success' ? 'alert-success' : 
                      type === 'error' ? 'alert-danger' : 
                      type === 'warning' ? 'alert-warning' : 'alert-info';
    
    const notification = $(`
        <div class="alert ${alertClass} alert-dismissible fade show alert-slide-down" role="alert">
            ${message}
            <button type="button" class="close" data-dismiss="alert">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    `);
    
    $('#notifications-container').prepend(notification);
    
    // Auto-dismiss after duration
    notificationTimeout = setTimeout(() => {
        notification.fadeOut();
    }, duration);
}

/**
 * Show loading overlay
 */
function showLoading() {
    $('#loading-overlay').fadeIn();
}

/**
 * Hide loading overlay
 */
function hideLoading() {
    $('#loading-overlay').fadeOut();
}

/**
 * Validate form
 */
function validateForm(formId) {
    let isValid = true;
    
    $(`#${formId} input[required], #${formId} select[required], #${formId} textarea[required]`).each(function() {
        const $field = $(this);
        const value = $field.val().trim();
        
        if (!value) {
            $field.addClass('is-invalid');
            isValid = false;
        } else {
            $field.removeClass('is-invalid');
        }
        
        // Email validation
        if ($field.attr('type') === 'email' && value) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                $field.addClass('is-invalid');
                isValid = false;
            }
        }
        
        // Password strength validation
        if ($field.attr('id') && $field.attr('id').includes('password') && value) {
            const passwordRegex = /^(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{8,}$/;
            if (!passwordRegex.test(value)) {
                $field.addClass('is-invalid');
                isValid = false;
            }
        }
    });
    
    return isValid;
}

/**
 * Reset form
 */
function resetForm(formId) {
    if (formId) {
        $(`#${formId}`)[0].reset();
        $(`#${formId} .is-invalid`).removeClass('is-invalid');
    }
}

/**
 * Confirm delete action
 */
function confirmDelete(message = 'Are you sure you want to delete this item?') {
    return confirm(message);
}

/**
 * Export data to CSV
 */
function exportToCSV(data, filename) {
    const csv = convertToCSV(data);
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Convert data to CSV format
 */
function convertToCSV(data) {
    if (!data || data.length === 0) return '';
    
    const headers = Object.keys(data[0]);
    const csvHeaders = headers.join(',');
    
    const csvRows = data.map(row => {
        return headers.map(header => {
            const value = row[header];
            return typeof value === 'string' && value.includes(',') ? `"${value}"` : value;
        }).join(',');
    });
    
    return [csvHeaders, ...csvRows].join('\n');
}

/**
 * Print page
 */
function printPage() {
    window.print();
}

/**
 * Toggle dark mode
 */
function toggleDarkMode() {
    $('body').toggleClass('dark-mode');
    applyChartTheme();
    
    $.ajax({
        url: `${appBasePath}ajax/toggle-dark-mode.php`,
        method: 'POST',
        beforeSend: function(xhr) {
            xhr.setRequestHeader('X-CSRF-Token', $('meta[name="csrf-token"]').attr('content'));
        },
        success: function(response) {
            if (response.success) {
                showNotification(response.message, 'success', 2000);
            }
        }
    });
}

function applyChartTheme() {
    const darkMode = $('body').hasClass('dark-mode');
    const textColor = darkMode ? '#e6edf5' : '#495057';
    const gridColor = darkMode ? 'rgba(230, 237, 245, 0.15)' : 'rgba(0,0,0,0.1)';

    if (window.Chart) {
        Chart.defaults.color = textColor;
        Chart.defaults.borderColor = gridColor;
        Object.values(Chart.instances || {}).forEach((instance) => {
            if (!instance || !instance.options) return;
            if (instance.options.plugins?.legend?.labels) {
                instance.options.plugins.legend.labels.color = textColor;
            }
            if (instance.options.scales) {
                Object.keys(instance.options.scales).forEach((axis) => {
                    if (instance.options.scales[axis].ticks) {
                        instance.options.scales[axis].ticks.color = textColor;
                    }
                    if (instance.options.scales[axis].grid) {
                        instance.options.scales[axis].grid.color = gridColor;
                    }
                });
            }
            instance.update('none');
        });
    }
}

function viewTransaction(type, id) {
    const modal = $('#transactionDetailModal');
    if (!modal.length) return;

    $('#transaction-detail-content').html('<p class="text-muted mb-0">Memuat detail transaksi...</p>');
    modal.modal('show');

    $.ajax({
        url: `${appBasePath}ajax/transaction-detail.php`,
        method: 'GET',
        dataType: 'json',
        data: { type, id },
        success: function(response) {
            if (!response.success || !response.data) {
                $('#transaction-detail-content').html('<div class="alert alert-warning mb-0">Detail transaksi tidak ditemukan.</div>');
                return;
            }

            const trx = response.data;
            const badgeClass = trx.type === 'income' ? 'badge-success' : 'badge-danger';
            const html = `
                <table class="table table-sm table-bordered mb-0">
                    <tr><th width="35%">Tipe</th><td><span class="badge ${badgeClass}">${trx.type}</span></td></tr>
                    <tr><th>ID</th><td>${trx.id}</td></tr>
                    <tr><th>Tanggal</th><td>${trx.date}</td></tr>
                    <tr><th>Kategori</th><td>${trx.category}</td></tr>
                    <tr><th>Nominal</th><td>${trx.amount}</td></tr>
                    <tr><th>Deskripsi</th><td>${trx.description}</td></tr>
                    <tr><th>Dibuat</th><td>${trx.created_at}</td></tr>
                </table>
            `;
            $('#transaction-detail-content').html(html);
        },
        error: function() {
            $('#transaction-detail-content').html('<div class="alert alert-danger mb-0">Gagal mengambil detail transaksi.</div>');
        }
    });
}

/**
 * Initialize range slider
 */
function initializeRangeSlider(sliderId, options = {}) {
    const slider = document.getElementById(sliderId);
    if (!slider) return;
    
    const defaultOptions = {
        min: 0,
        max: 100,
        value: 50,
        step: 1,
        onChange: function(value) {
            slider.style.setProperty('--value', `${(value / this.max) * 100}%`);
        }
    };
    
    const config = Object.assign(defaultOptions, options);
    
    slider.min = config.min;
    slider.max = config.max;
    slider.value = config.value;
    slider.step = config.step;
    
    slider.style.setProperty('--value', `${(config.value / config.max) * 100}%`);
    
    slider.addEventListener('input', function() {
        config.onChange.call(this, this.value);
    });
}

/**
 * Debounce function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Throttle function
 */
function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

/**
 * Copy to clipboard
 */
function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            showNotification('Copied to clipboard!', 'success', 2000);
        }).catch(() => {
            fallbackCopyTextToClipboard(text);
        });
    } else {
        fallbackCopyTextToClipboard(text);
    }
}

/**
 * Fallback copy to clipboard
 */
function fallbackCopyTextToClipboard(text) {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        document.execCommand('copy');
        showNotification('Copied to clipboard!', 'success', 2000);
    } catch (err) {
        showNotification('Failed to copy to clipboard', 'error');
    }
    
    document.body.removeChild(textArea);
}

/**
 * Generate random color
 */
function generateRandomColor() {
    const letters = '0123456789ABCDEF';
    let color = '#';
    for (let i = 0; i < 6; i++) {
        color += letters[Math.floor(Math.random() * 16)];
    }
    return color;
}

/**
 * Format file size
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Get URL parameters
 */
function getUrlParameter(name) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(name);
}

/**
 * Set URL parameter
 */
function setUrlParameter(name, value) {
    const url = new URL(window.location);
    url.searchParams.set(name, value);
    window.history.replaceState({}, '', url);
}

/**
 * Remove URL parameter
 */
function removeUrlParameter(name) {
    const url = new URL(window.location);
    url.searchParams.delete(name);
    window.history.replaceState({}, '', url);
}

/**
 * Check if element is in viewport
 */
function isElementInViewport(element) {
    const rect = element.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}

/**
 * Scroll to element
 */
function scrollToElement(element, offset = 0) {
    const elementTop = $(element).offset().top - offset;
    $('html, body').animate({
        scrollTop: elementTop
    }, 500);
}

/**
 * Initialize charts with responsive options
 */
function initializeChart(canvasId, config) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    
    const defaultConfig = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    padding: 20,
                    font: {
                        size: 12
                    }
                }
            },
            tooltip: {
                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                padding: 12,
                cornerRadius: 4,
                titleFont: {
                    size: 14
                },
                bodyFont: {
                    size: 12
                }
            }
        },
        scales: {
            x: {
                grid: {
                    display: false
                }
            },
            y: {
                beginAtZero: true,
                grid: {
                    borderDash: [2, 2]
                }
            }
        }
    };
    
    const finalConfig = Object.assign(defaultConfig, config);
    const chart = new Chart(ctx, finalConfig);
    
    // Store instance for later updates
    chartInstances[canvasId] = chart;
    
    return chart;
}

/**
 * Destroy chart instance
 */
function destroyChart(canvasId) {
    if (chartInstances[canvasId]) {
        chartInstances[canvasId].destroy();
        delete chartInstances[canvasId];
    }
}

/**
 * Export chart as image
 */
function exportChart(canvasId, filename) {
    const chart = chartInstances[canvasId];
    if (!chart) return;
    
    const url = chart.toBase64Image();
    const link = document.createElement('a');
    link.download = filename || 'chart.png';
    link.href = url;
    link.click();
}

// Global error handler
window.addEventListener('error', function(e) {
    console.error('JavaScript error:', e.error);
    showNotification('An unexpected error occurred. Please try again.', 'error');
});

/**
 * Initialize fixed sidebar functionality
 */
function initializeFixedSidebar() {
    // Create overlay backdrop element
    if (!$('#sidebar-overlay').length) {
        $('body').append('<div id="sidebar-overlay" style="display: none;"></div>');
    }
    
    // Handle mobile sidebar toggle with overlay
    $(document).on('click', '[data-widget="pushmenu"]', function(e) {
        e.preventDefault();
        
        if ($(window).width() <= 768) {
            // Mobile: Show/hide overlay
            if ($('body').hasClass('sidebar-open')) {
                $('#sidebar-overlay').fadeOut(300);
            } else {
                $('#sidebar-overlay').fadeIn(300);
            }
        }
    });
    
    // Close sidebar when clicking overlay
    $('#sidebar-overlay').on('click', function() {
        closeSidebar();
    });
    
    // Close sidebar when clicking outside on mobile (enhanced click outside)
    $(document).on('click', function(e) {
        // Only apply on mobile and when sidebar is open
        if ($(window).width() <= 768 && $('body').hasClass('sidebar-open')) {
            // Check if click is outside sidebar and not on hamburger menu
            if (!$(e.target).closest('.main-sidebar, [data-widget="pushmenu"]').length) {
                closeSidebar();
            }
        }
    });
    
    // Handle window resize
    $(window).on('resize', function() {
        if ($(window).width() > 768) {
            // Desktop: Hide overlay and reset sidebar
            $('#sidebar-overlay').hide();
            $('body').removeClass('sidebar-open');
        }
    });
    
    // Add overlay styles
    $('<style>')
        .prop('type', 'text/css')
        .html(`
            #sidebar-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.3);
                z-index: 1038;
                cursor: pointer;
            }
        `)
        .appendTo('head');
}

/**
 * Close sidebar function
 */
function closeSidebar() {
    $('body').removeClass('sidebar-open');
    $('#sidebar-overlay').fadeOut(300);
}

/**
 * Open sidebar function
 */
function openSidebar() {
    $('body').addClass('sidebar-open');
    if ($(window).width() <= 768) {
        $('#sidebar-overlay').fadeIn(300);
    }
}

/**
 * Toggle sidebar function
 */
function toggleSidebar() {
    if ($('body').hasClass('sidebar-open')) {
        closeSidebar();
    } else {
        openSidebar();
    }
}

// Unhandled promise rejection handler
window.addEventListener('unhandledrejection', function(e) {
    console.error('Unhandled promise rejection:', e.reason);
    showNotification('An unexpected error occurred. Please try again.', 'error');
});

$(document).ready(function() {
    applyChartTheme();
});
