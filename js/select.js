(function() {
  function initCustomSelect(selectId, inputId) {
    const container = document.getElementById(selectId);
    if (!container) return;
    
    const trigger = container.querySelector('.custom-select-trigger');
    const dropdown = container.querySelector('.custom-select-dropdown');
    const options = container.querySelectorAll('.custom-select-option');
    const hiddenInput = document.getElementById(inputId);
    const selectedTextSpan = trigger.querySelector('.selected-text');
    
    // Set initial selected state
    const currentValue = hiddenInput.value;
    options.forEach(opt => {
      if (opt.dataset.value === currentValue) {
        opt.classList.add('selected');
        selectedTextSpan.textContent = opt.textContent;
      }
    });
    
    // Toggle dropdown
    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      const isOpen = dropdown.classList.contains('show');
      // Close all other dropdowns
      document.querySelectorAll('.custom-select-dropdown.show').forEach(d => {
        if (d !== dropdown) d.classList.remove('show');
      });
      document.querySelectorAll('.custom-select-trigger.open').forEach(t => {
        if (t !== trigger) t.classList.remove('open');
      });
      
      dropdown.classList.toggle('show');
      trigger.classList.toggle('open');
    });
    
    // Select option
    options.forEach(opt => {
      opt.addEventListener('click', () => {
        const value = opt.dataset.value;
        const text = opt.textContent;
        
        hiddenInput.value = value;
        selectedTextSpan.textContent = text;
        
        // Update selected class
        options.forEach(o => o.classList.remove('selected'));
        opt.classList.add('selected');
        
        // Close dropdown
        dropdown.classList.remove('show');
        trigger.classList.remove('open');
        
        // Auto-submit the form
        document.getElementById('filterForm').submit();
      });
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
      if (!container.contains(e.target)) {
        dropdown.classList.remove('show');
        trigger.classList.remove('open');
      }
    });
  }
  
  initCustomSelect('statusSelect', 'statusInput');
})();