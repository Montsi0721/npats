(function() {
    'use strict';

    function initSpotlightEffect() {
        const spotlightElements = document.querySelectorAll('.hover-card, .applicant-card, .stat-card, .quick-action-card, [data-spotlight], .od-stat');
        
        spotlightElements.forEach(el => {
            // Create spotlight overlay if not exists
            let spotlight = el.querySelector('.sc-spotlight');
            if (!spotlight) {
                spotlight = document.createElement('div');
                spotlight.className = 'sc-spotlight';
                // Ensure element has proper positioning
                if (getComputedStyle(el).position === 'static') {
                    el.style.position = 'relative';
                }
                el.style.overflow = 'hidden';
                el.appendChild(spotlight);
            }
            
            // Mouse move handler for spotlight
            el.addEventListener('mousemove', function(e) {
                const rect = this.getBoundingClientRect();
                const x = ((e.clientX - rect.left) / rect.width) * 100;
                const y = ((e.clientY - rect.top) / rect.height) * 100;
                
                // Update CSS variables for the gradient
                this.style.setProperty('--x', x + '%');
                this.style.setProperty('--y', y + '%');
                
                // Update spotlight position and opacity
                spotlight.style.background = `radial-gradient(circle at ${x}% ${y}%, rgba(59, 130, 246, 0.12) 0%, transparent 60%)`;
                spotlight.style.opacity = '1';
            });
            
            el.addEventListener('mouseleave', function() {
                spotlight.style.opacity = '0';
            });
        });
    }
    
    function initTiltEffect() {
        const tiltCards = document.querySelectorAll('.od-stat');
        
        tiltCards.forEach(card => {
            let tiltX = 0, tiltY = 0;
            let targetX = 0, targetY = 0;
            let animationFrame = null;
            
            function updateTransform() {
                tiltX += (targetX - tiltX) * 0.1;
                tiltY += (targetY - tiltY) * 0.1;
                card.style.transform = `perspective(1000px) rotateX(${tiltX}deg) rotateY(${tiltY}deg) translateY(-4px)`;
                animationFrame = requestAnimationFrame(updateTransform);
            }
            
            card.addEventListener('mousemove', function(e) {
                const rect = this.getBoundingClientRect();
                const centerX = rect.left + rect.width / 2;
                const centerY = rect.top + rect.height / 2;
                const deltaX = (e.clientX - centerX) / (rect.width / 2);
                const deltaY = (e.clientY - centerY) / (rect.height / 2);
                
                targetX = -deltaY * 4;
                targetY = deltaX * 4;
                
                if (!animationFrame) {
                    animationFrame = requestAnimationFrame(updateTransform);
                }
            });
            
            card.addEventListener('mouseleave', function() {
                targetX = 0;
                targetY = 0;
                if (animationFrame) {
                    cancelAnimationFrame(animationFrame);
                    animationFrame = null;
                }
                card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) translateY(0px)';
                tiltX = 0;
                tiltY = 0;
            });
        });
    }

    /**
     * Opens a modal by ID
     * @param {string} modalId - ID of the modal element
     */
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    }
    
    /**
     * Closes a modal by ID
     * @param {string} modalId - ID of the modal element
     */
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
    
    function initModals() {
        // Open modals from buttons with data-modal-open attribute
        document.querySelectorAll('[data-modal-open]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                // Prevent default if it's a button that might also have href
                e.preventDefault();
                const modalId = btn.getAttribute('data-modal-open');
                openModal(modalId);
            });
        });
        
        // Close modals with close buttons
        document.querySelectorAll('[data-modal-close]').forEach(btn => {
            btn.addEventListener('click', () => {
                const modalId = btn.getAttribute('data-modal-close');
                closeModal(modalId);
            });
        });
        
        // Close modal when clicking overlay background
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });
        
        // Escape key to close modal
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                    modal.classList.remove('active');
                    document.body.style.overflow = '';
                });
            }
        });
    }

    function initPasswordToggles() {
        document.querySelectorAll('.eye-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const input = btn.closest('.input-wrap')?.querySelector('input');
                const icon = btn.querySelector('i');
                if (input && icon) {
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        input.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                }
            });
        });
    }

    
    /**
     * Draws a sparkline chart on a canvas element
     * @param {string} id - Canvas element ID
     * @param {number[]} data - Array of data points
     * @param {string} color - Line color (CSS color)
     */
    function drawSparkline(id, data, color) {
        const canvas = document.getElementById(id);
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        const W = canvas.width, H = canvas.height;
        const max = Math.max(...data, 1);
        
        // Calculate points
        const points = data.map((v, i) => ({
            x: 1 + (i / (data.length - 1)) * (W - 2),
            y: H - 3 - (v / max) * (H - 7)
        }));
        
        ctx.clearRect(0, 0, W, H);
        
        // Helper function for smooth curve
        function drawSmoothCurve(points, isFill = false) {
            if (points.length < 2) return;
            
            ctx.beginPath();
            ctx.moveTo(points[0].x, points[0].y);
            
            for (let i = 1; i < points.length - 1; i++) {
                const mx = (points[i].x + points[i + 1].x) / 2;
                const my = (points[i].y + points[i + 1].y) / 2;
                ctx.quadraticCurveTo(points[i].x, points[i].y, mx, my);
            }
            
            ctx.lineTo(points[points.length - 1].x, points[points.length - 1].y);
            
            if (isFill) {
                ctx.lineTo(points[points.length - 1].x, H);
                ctx.lineTo(points[0].x, H);
                ctx.closePath();
            }
        }
        
        // Draw filled area with gradient
        const gradient = ctx.createLinearGradient(0, 0, 0, H);
        gradient.addColorStop(0, color + '60');
        gradient.addColorStop(1, color + '00');
        
        ctx.save();
        drawSmoothCurve(points, true);
        ctx.fillStyle = gradient;
        ctx.fill();
        ctx.restore();
        
        // Draw line
        drawSmoothCurve(points, false);
        ctx.strokeStyle = color;
        ctx.lineWidth = 1.6;
        ctx.lineJoin = 'round';
        ctx.stroke();
        
        // Draw endpoint dot
        const lastPoint = points[points.length - 1];
        ctx.beginPath();
        ctx.arc(lastPoint.x, lastPoint.y, 2.5, 0, Math.PI * 2);
        ctx.fillStyle = color;
        ctx.fill();
        
        ctx.beginPath();
        ctx.arc(lastPoint.x, lastPoint.y, 4.5, 0, Math.PI * 2);
        ctx.fillStyle = color + '33';
        ctx.fill();
    }
    
    function initSparklines() {
        // This data should be set by PHP in a window variable
        // Default fallback if not set by backend
        const rawData = window.sparklineData || [0, 0, 0, 0, 0, 0, 0];
        
        drawSparkline('sp0', rawData, '#60A5FA');
        drawSparkline('sp1', rawData.map(v => Math.max(0, v - Math.round(v * 0.6))), '#F59E0B');
        drawSparkline('sp2', rawData.map(v => Math.round(v * 0.55)), '#2DD4BF');
        drawSparkline('sp3', rawData.map(v => Math.round(v * 0.2)), '#34D399');
        drawSparkline('sp4', rawData.map((v, i) => i < 3 ? Math.round(v * 0.1) : Math.round(v * 0.5)), '#34D399');
    }

    /**
     * Draws a donut chart on the officer dashboard
     * @param {Object} segments - Object with status counts
     */
    function drawDonutChart(segments) {
        const canvas = document.getElementById('od-donut');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        const isDark = document.documentElement.dataset.theme !== 'light';
        
        const segs = [
            { v: segments.pending || 0, c: '#F59E0B' },
            { v: segments.inProgress || 0, c: '#60A5FA' },
            { v: segments.ready || 0, c: '#2DD4BF' },
            { v: segments.completed || 0, c: '#34D399' }
        ];
        
        const total = segs.reduce((sum, s) => sum + s.v, 0) || 1;
        const cx = 42, cy = 42, outerRadius = 37, innerRadius = 24;
        let startAngle = -Math.PI / 2;
        
        // Check if all segments are zero
        if (segs.every(s => s.v === 0)) {
            ctx.beginPath();
            ctx.arc(cx, cy, outerRadius, 0, Math.PI * 2);
            ctx.arc(cx, cy, innerRadius, Math.PI * 2, 0, true);
            ctx.closePath();
            ctx.fillStyle = isDark ? '#1C2333' : '#EEF1F8';
            ctx.fill();
        } else {
            segs.forEach(seg => {
                if (seg.v === 0) return;
                const sweepAngle = (seg.v / total) * Math.PI * 2;
                
                ctx.beginPath();
                ctx.arc(cx, cy, outerRadius, startAngle, startAngle + sweepAngle);
                ctx.arc(cx, cy, innerRadius, startAngle + sweepAngle, startAngle, true);
                ctx.closePath();
                ctx.fillStyle = seg.c;
                ctx.fill();
                
                startAngle += sweepAngle + 0.03;
            });
        }
        
        // Draw center text
        const totalApps = segs.reduce((sum, s) => sum + s.v, 0);
        ctx.fillStyle = isDark ? '#E2E8F4' : '#1A2238';
        ctx.font = '800 14px system-ui';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(totalApps, cx, cy - 1);
        
        ctx.font = '500 9px system-ui';
        ctx.fillStyle = isDark ? '#667085' : '#6B7898';
        ctx.fillText('total', cx, cy + 9);
    }

    function initAnimatedCounters() {
        document.querySelectorAll('.od-stat-num[data-target]').forEach(el => {
            const target = parseInt(el.dataset.target, 10);
            if (isNaN(target) || target === 0) return;
            
            let current = 0;
            const step = Math.ceil(target / 30);
            
            const interval = setInterval(() => {
                current = Math.min(current + step, target);
                el.textContent = current;
                if (current >= target) {
                    clearInterval(interval);
                }
            }, 30);
        });
    }

    function initStatCards() {
        // Stat cards can have custom click handlers
        // Add any stat card specific behavior here
        const statCards = document.querySelectorAll('.stat-card');
        statCards.forEach(card => {
            card.addEventListener('click', function(e) {
                // If card has a data-href attribute, navigate there
                const href = this.getAttribute('data-href');
                if (href && !e.target.closest('a')) {
                    window.location.href = href;
                }
            });
        });
    }

    function initApplicationTracking() {
        // Update progress bars with animation
        const progressBars = document.querySelectorAll('.progress-fill, .stage-progress');
        progressBars.forEach(bar => {
            const targetWidth = bar.getAttribute('data-progress') || bar.style.getPropertyValue('--target-w');
            if (targetWidth) {
                // Trigger animation after a short delay
                setTimeout(() => {
                    bar.style.width = targetWidth;
                }, 100);
            }
        });
        
        // Add pulse animation to active stages
        const activeStages = document.querySelectorAll('.stage-item.active .stage-dot');
        activeStages.forEach(dot => {
            dot.style.animation = 'stagePulse 1.5s infinite';
        });
    }

    function initLiveClock() {
        const timeElement = document.querySelector('.live-time, [data-live-time]');
        if (!timeElement) return;
        
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                second: '2-digit'
            });
            if (timeElement) {
                timeElement.textContent = timeString;
            }
        }
        
        updateTime();
        setInterval(updateTime, 1000);
    }

    function initThemeObserver() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.attributeName === 'data-theme') {
                    // Redraw charts when theme changes
                    if (document.getElementById('od-donut')) {
                        // Re-draw donut if data is available
                        const pending = parseInt(document.querySelector('.od-leg-val')?.textContent || '0', 10);
                        // This would need proper data re-injection
                    }
                }
            });
        });
        
        observer.observe(document.documentElement, { attributes: true });
    }

    function initTooltips() {
        const tooltipElements = document.querySelectorAll('[data-tooltip]');
        
        tooltipElements.forEach(el => {
            let tooltip = null;
            
            el.addEventListener('mouseenter', (e) => {
                const text = el.getAttribute('data-tooltip');
                if (!text) return;
                
                tooltip = document.createElement('div');
                tooltip.className = 'custom-tooltip';
                tooltip.textContent = text;
                tooltip.style.cssText = `
                    position: fixed;
                    background: rgba(0,0,0,0.8);
                    color: white;
                    padding: 4px 8px;
                    border-radius: 4px;
                    font-size: 12px;
                    z-index: 10001;
                    pointer-events: none;
                    white-space: nowrap;
                `;
                document.body.appendChild(tooltip);
                
                const rect = el.getBoundingClientRect();
                tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
                tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
            });
            
            el.addEventListener('mouseleave', () => {
                if (tooltip) {
                    tooltip.remove();
                    tooltip = null;
                }
            });
        });
    }

    function initScrollReveal() {
        const revealElements = document.querySelectorAll('.reveal-on-scroll');
        
        if (revealElements.length === 0) return;
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });
        
        revealElements.forEach(el => observer.observe(el));
    }

    function initResponsiveTables() {
        const tables = document.querySelectorAll('.table-wrapper, .od-table-wrap');
        
        tables.forEach(table => {
            // Check if table needs scrolling
            function checkScroll() {
                if (table.scrollWidth > table.clientWidth) {
                    table.classList.add('has-scroll');
                } else {
                    table.classList.remove('has-scroll');
                }
            }
            
            checkScroll();
            window.addEventListener('resize', checkScroll);
        });
    }

    function initLoadingStates() {
        const forms = document.querySelectorAll('form[data-loading]');
        
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    const originalText = submitBtn.innerHTML;
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Loading...';
                    
                    // Restore after form submission (will be handled by page reload)
                    // This is a fallback in case submission fails
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }, 5000);
                }
            });
        });
    }

    /**
     * Displays a temporary toast notification
     * @param {string} message - Notification message
     * @param {string} type - 'success', 'error', 'info', 'warning'
     */
    window.showToast = function(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type}`;
        toast.textContent = message;
        toast.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-size: 14px;
            z-index: 10002;
            animation: slideInRight 0.3s ease;
            background: ${type === 'success' ? '#10B981' : type === 'error' ? '#EF4444' : type === 'warning' ? '#F59E0B' : '#3B82F6'};
        `;
        
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    };

    // Expose global functions for dynamic data updates
    window.Dashboard = {
        updateSparklineData: function(data) {
            window.sparklineData = data;
            initSparklines();
        },
        updateDonutData: function(segments) {
            drawDonutChart(segments);
        },
        refreshStats: function() {
            initAnimatedCounters();
        },
        showNotification: window.showToast
    };

    function init() {
        // Core UI interactions
        initSpotlightEffect();
        initTiltEffect();
        initModals();
        initPasswordToggles();
        initStatCards();
        
        // Charts and data visualization
        initSparklines();
        initAnimatedCounters();
        
        // Tracking and utilities
        initApplicationTracking();
        initLiveClock();
        initThemeObserver();
        initTooltips();
        
        // Responsive and loading
        initScrollReveal();
        initResponsiveTables();
        initLoadingStates();
        
        // Donut chart needs data from PHP - will be called after page load
        // Data should be set via window.donutData by PHP
        if (window.donutData) {
            drawDonutChart(window.donutData);
        }
        
        // Add custom animation keyframes if not present
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOutRight {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            .reveal-on-scroll {
                opacity: 0;
                transform: translateY(20px);
                transition: all 0.5s ease;
            }
            .reveal-on-scroll.revealed {
                opacity: 1;
                transform: translateY(0);
            }
            .table-wrapper.has-scroll::after,
            .od-table-wrap.has-scroll::after {
                content: '← scroll →';
                position: absolute;
                bottom: 8px;
                right: 8px;
                font-size: 10px;
                color: var(--muted);
                background: var(--bg-alt);
                padding: 2px 6px;
                border-radius: 4px;
                opacity: 0.7;
            }
            .table-wrapper, .od-table-wrap {
                position: relative;
            }
        `;
        document.head.appendChild(style);
    }
    
    // Run initialization when DOM is fully loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
})();