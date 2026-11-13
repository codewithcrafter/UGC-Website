/**
 * MY UGC VIDEO — Vanilla JavaScript Interactions
 * Handles Header Scroll, Desktop/Mobile Hover & Click Dropdowns,
 * Mobile Drawer, FAQ Accordion, Blog Category Filtering, Load More,
 * Contact Form Validation, and Newsletter Submissions.
 */

document.addEventListener('DOMContentLoaded', () => {
  initStickyHeader();
  initMobileNav();
  initMobileDropdown();
  initFaqAccordion();
  initBlogFilters();
  initContactForm();
  initNewsletterForms();
  setActiveNavLinks();
  initInteractiveServicesDirectory();
  initServicesSlider();
  initDualInquiryForms();
  initVideoModal();
  initUgcCategoryTabs();
  initHomepagePortfolioFilter();
  // initTestimonialSlider(); // Testimonials section removed from homepage
  initHomepageInquiryForm();
});

/* --------------------------------------------------------------------------
   1. STICKY HEADER SCROLL EFFECT
   -------------------------------------------------------------------------- */
function initStickyHeader() {
  const header = document.querySelector('.site-header');
  if (!header) return;

  const handleScroll = () => {
    if (window.scrollY > 30) {
      header.classList.add('scrolled');
    } else {
      header.classList.remove('scrolled');
    }
  };

  window.addEventListener('scroll', handleScroll, { passive: true });
  handleScroll(); // Initial check
}

/* --------------------------------------------------------------------------
   2. MOBILE NAVIGATION DRAWER
   -------------------------------------------------------------------------- */
function initMobileNav() {
  const toggleBtn = document.querySelector('.nav-toggle');
  const drawer = document.querySelector('.mobile-drawer');
  const overlay = document.querySelector('.drawer-overlay');
  if (!toggleBtn || !drawer || !overlay) return;

  const openDrawer = () => {
    toggleBtn.setAttribute('aria-expanded', 'true');
    drawer.classList.add('active');
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  };

  const closeDrawer = () => {
    toggleBtn.setAttribute('aria-expanded', 'false');
    drawer.classList.remove('active');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  };

  toggleBtn.addEventListener('click', () => {
    const isExpanded = toggleBtn.getAttribute('aria-expanded') === 'true';
    if (isExpanded) {
      closeDrawer();
    } else {
      openDrawer();
    }
  });

  overlay.addEventListener('click', closeDrawer);

  // Close when clicking nav links inside drawer (except dropdown toggle)
  const drawerLinks = drawer.querySelectorAll('a:not(.mobile-dropdown-toggle)');
  drawerLinks.forEach(link => {
    link.addEventListener('click', () => {
      // Small timeout so anchor jump happens cleanly
      setTimeout(closeDrawer, 120);
    });
  });

  // Close on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && drawer.classList.contains('active')) {
      closeDrawer();
    }
  });
}

/* --------------------------------------------------------------------------
   3. MOBILE SERVICES DROPDOWN ACCORDION
   -------------------------------------------------------------------------- */
function initMobileDropdown() {
  const toggleTrigger = document.querySelector('.mobile-dropdown-toggle');
  const dropdownContent = document.querySelector('.mobile-dropdown-content');
  if (!toggleTrigger || !dropdownContent) return;

  toggleTrigger.addEventListener('click', (e) => {
    e.preventDefault();
    const isOpen = dropdownContent.classList.contains('open');
    if (isOpen) {
      dropdownContent.classList.remove('open');
      toggleTrigger.setAttribute('aria-expanded', 'false');
      const arrow = toggleTrigger.querySelector('.dropdown-arrow');
      if (arrow) arrow.style.transform = 'rotate(0deg)';
    } else {
      dropdownContent.classList.add('open');
      toggleTrigger.setAttribute('aria-expanded', 'true');
      const arrow = toggleTrigger.querySelector('.dropdown-arrow');
      if (arrow) arrow.style.transform = 'rotate(180deg)';
    }
  });
}

/* --------------------------------------------------------------------------
   4. FAQ ACCORDION COMPONENT (ACCESSIBLE SINGLE-OPEN ARCHITECTURE)
   -------------------------------------------------------------------------- */
function initFaqAccordion() {
  const accordionSections = document.querySelectorAll('.faq-accordion');
  if (!accordionSections.length) return;

  accordionSections.forEach((section, secIndex) => {
    const items = section.querySelectorAll('.faq-item');
    if (!items.length) return;

    items.forEach((item, itemIndex) => {
      // Support both .faq-header and .faq-question triggers
      const trigger = item.querySelector('.faq-header, .faq-question, button');
      // Support both .faq-body and .faq-answer content containers
      const content = item.querySelector('.faq-body, .faq-answer');

      if (!trigger || !content) return;

      // Assign unique accessible IDs if not already provided in markup
      const panelId = content.id || `faq-panel-${secIndex + 1}-${itemIndex + 1}`;
      const triggerId = trigger.id || `faq-trigger-${secIndex + 1}-${itemIndex + 1}`;

      if (!content.id) content.id = panelId;
      if (!trigger.id) trigger.id = triggerId;

      trigger.setAttribute('aria-controls', panelId);
      trigger.setAttribute('aria-expanded', 'false');
      content.setAttribute('role', 'region');
      content.setAttribute('aria-labelledby', triggerId);

      // Explicitly ensure closed state on initialization
      item.classList.remove('active');
      content.style.maxHeight = null;

      // Single-open accordion click handler
      trigger.addEventListener('click', (e) => {
        e.preventDefault();
        const isOpen = item.classList.contains('active');

        // Close all other items strictly within this specific FAQ section
        items.forEach(otherItem => {
          if (otherItem !== item && otherItem.classList.contains('active')) {
            otherItem.classList.remove('active');
            const otherTrigger = otherItem.querySelector('.faq-header, .faq-question, button');
            const otherContent = otherItem.querySelector('.faq-body, .faq-answer');
            if (otherTrigger) otherTrigger.setAttribute('aria-expanded', 'false');
            if (otherContent) otherContent.style.maxHeight = null;
          }
        });

        // Toggle the clicked item
        if (isOpen) {
          item.classList.remove('active');
          trigger.setAttribute('aria-expanded', 'false');
          content.style.maxHeight = null;
        } else {
          item.classList.add('active');
          trigger.setAttribute('aria-expanded', 'true');
          // Animate opening smoothly to scrollHeight
          content.style.maxHeight = content.scrollHeight + 'px';
        }
      });
    });
  });

  // Handle window resize dynamically so open panels adapt cleanly
  let resizeTimer;
  window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
      document.querySelectorAll('.faq-item.active').forEach(activeItem => {
        const activeContent = activeItem.querySelector('.faq-body, .faq-answer');
        if (activeContent) {
          activeContent.style.maxHeight = activeContent.scrollHeight + 'px';
        }
      });
    }, 100);
  });
}

/* --------------------------------------------------------------------------
   5. BLOG CATEGORY FILTERING, LIVE SEARCH & LOAD MORE
   -------------------------------------------------------------------------- */
function initBlogFilters() {
  const filterButtons = document.querySelectorAll('.category-filter-btn, .filter-btn, .blog-category-btn');
  const blogCards = document.querySelectorAll('#blogGrid .blog-card, .blog-editorial-grid .blog-card, .blog-grid .blog-card');
  const searchInput = document.querySelector('#blogSearchInput');
  const searchClear = document.querySelector('#blogSearchClear');
  const loadMoreBtn = document.querySelector('#loadMoreBtn');
  const loadMoreWrap = document.querySelector('#loadMoreWrap, .blog-load-more-wrap');
  const noResultsBox = document.querySelector('#noBlogsFound');
  const resetFiltersBtn = document.querySelector('#resetBlogFiltersBtn');
  const resultsSummary = document.querySelector('#blogResultsSummary');

  if (!blogCards.length) return;

  let currentCategory = 'all';
  let searchQuery = '';
  const initialVisibleCount = 6;
  let currentlyShown = initialVisibleCount;

  // Pre-index cards for fast, case-insensitive search
  const cardsData = Array.from(blogCards).map(card => {
    const titleEl = card.querySelector('.blog-card-title, h3, h4');
    const descEl = card.querySelector('.blog-card-excerpt, p');
    const category = (card.getAttribute('data-category') || '').toLowerCase().trim();
    const keywords = (card.getAttribute('data-keywords') || '').toLowerCase().trim();
    const title = titleEl ? titleEl.textContent.toLowerCase().trim() : '';
    const desc = descEl ? descEl.textContent.toLowerCase().trim() : '';
    const textAll = card.textContent.toLowerCase();

    return {
      element: card,
      category,
      keywords,
      title,
      desc,
      searchIndex: `${title} ${desc} ${category} ${keywords} ${textAll}`
    };
  });

  function updateDisplay() {
    let matchedCards = [];

    cardsData.forEach(item => {
      // Category match
      const matchesCategory = (currentCategory === 'all' || item.category === currentCategory);

      // Search match
      let matchesSearch = true;
      if (searchQuery) {
        matchesSearch = item.searchIndex.includes(searchQuery);
      }

      if (matchesCategory && matchesSearch) {
        matchedCards.push(item);
      } else {
        item.element.style.display = 'none';
      }
    });

    // Update matched cards visibility based on pagination
    if (matchedCards.length === 0) {
      if (noResultsBox) noResultsBox.style.display = 'block';
      if (loadMoreWrap) loadMoreWrap.style.display = 'none';
      if (resultsSummary) resultsSummary.textContent = 'No articles match your search or filter.';
    } else {
      if (noResultsBox) noResultsBox.style.display = 'none';

      matchedCards.forEach((item, index) => {
        if (index < currentlyShown) {
          item.element.style.display = 'flex';
          item.element.style.opacity = '1';
        } else {
          item.element.style.display = 'none';
        }
      });

      // Show/hide Load More button
      if (loadMoreWrap) {
        if (matchedCards.length <= currentlyShown) {
          loadMoreWrap.style.display = 'none';
        } else {
          loadMoreWrap.style.display = 'block';
        }
      }

      if (resultsSummary) {
        const visibleNow = Math.min(currentlyShown, matchedCards.length);
        if (searchQuery || currentCategory !== 'all') {
          resultsSummary.textContent = `Showing ${visibleNow} of ${matchedCards.length} matching article${matchedCards.length === 1 ? '' : 's'}`;
        } else {
          resultsSummary.textContent = '';
        }
      }
    }
  }

  // Category filter handlers
  if (filterButtons.length) {
    filterButtons.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        filterButtons.forEach(b => {
          b.classList.remove('active');
          b.setAttribute('aria-selected', 'false');
        });
        btn.classList.add('active');
        btn.setAttribute('aria-selected', 'true');

        currentCategory = (btn.getAttribute('data-category') || btn.getAttribute('data-filter') || 'all').toLowerCase();
        currentlyShown = initialVisibleCount;
        updateDisplay();
      });
    });
  }

  // Live search handler
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      searchQuery = e.target.value.toLowerCase().trim();
      if (searchClear) {
        searchClear.style.display = searchQuery ? 'block' : 'none';
      }
      currentlyShown = initialVisibleCount;
      updateDisplay();
    });

    if (searchClear) {
      searchClear.addEventListener('click', () => {
        searchInput.value = '';
        searchQuery = '';
        searchClear.style.display = 'none';
        currentlyShown = initialVisibleCount;
        searchInput.focus();
        updateDisplay();
      });
    }
  }

  // Reset filters handler
  if (resetFiltersBtn) {
    resetFiltersBtn.addEventListener('click', () => {
      if (searchInput) {
        searchInput.value = '';
        if (searchClear) searchClear.style.display = 'none';
      }
      searchQuery = '';
      currentCategory = 'all';

      filterButtons.forEach(b => {
        const cat = (b.getAttribute('data-category') || b.getAttribute('data-filter') || '').toLowerCase();
        if (cat === 'all') {
          b.classList.add('active');
          b.setAttribute('aria-selected', 'true');
        } else {
          b.classList.remove('active');
          b.setAttribute('aria-selected', 'false');
        }
      });

      currentlyShown = initialVisibleCount;
      updateDisplay();
    });
  }

  // Load More handler
  if (loadMoreBtn) {
    loadMoreBtn.addEventListener('click', () => {
      currentlyShown += initialVisibleCount;
      updateDisplay();
    });
  }

  // Initial layout calculation
  updateDisplay();
}

/* --------------------------------------------------------------------------
   6. CONTACT FORM VALIDATION & AUDIENCE TOGGLE
   -------------------------------------------------------------------------- */
function initContactForm() {
  const form = document.querySelector('#contactForm');
  const pathButtons = document.querySelectorAll('.path-btn');
  const roleSelect = document.querySelector('#inquiryRole');

  // Audience toggle (Brands vs Creators)
  if (pathButtons.length) {
    pathButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        pathButtons.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const role = btn.getAttribute('data-role');
        if (roleSelect && role) {
          roleSelect.value = role;
          handleRoleChange(role);
        }
      });
    });
  }

  if (roleSelect) {
    roleSelect.addEventListener('change', (e) => {
      const selected = e.target.value;
      pathButtons.forEach(btn => {
        if (btn.getAttribute('data-role') === selected) {
          btn.classList.add('active');
        } else {
          btn.classList.remove('active');
        }
      });
      handleRoleChange(selected);
    });
  }

  function handleRoleChange(role) {
    const companyLabel = document.querySelector('#companyLabel');
    const serviceLabel = document.querySelector('#serviceLabel');
    const msgPlaceholder = document.querySelector('#messageInput');

    if (role === 'creator') {
      if (companyLabel) companyLabel.innerHTML = 'Portfolio Link / Social Handle <span class="req">*</span>';
      if (serviceLabel) serviceLabel.textContent = 'Primary Creative Focus';
      if (msgPlaceholder) msgPlaceholder.placeholder = 'Tell us about your creator experience, platform niches, on-camera style, and availability...';
    } else {
      if (companyLabel) companyLabel.innerHTML = 'Brand / Company Name <span class="req">*</span>';
      if (serviceLabel) serviceLabel.textContent = 'Service Interested In';
      if (msgPlaceholder) msgPlaceholder.placeholder = 'Describe your campaign objectives, target audience, preferred video formats, and timeline...';
    }
  }

  if (!form) return;

  const statusAlert = document.querySelector('#formStatusAlert');

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    let isValid = true;

    // Reset previous errors
    form.querySelectorAll('.form-control').forEach(ctrl => ctrl.classList.remove('error'));
    form.querySelectorAll('.form-error-msg').forEach(msg => msg.classList.remove('visible'));
    if (statusAlert) {
      statusAlert.className = 'form-status-alert';
      statusAlert.style.display = 'none';
      statusAlert.textContent = '';
    }

    // Validate Full Name
    const nameInput = form.querySelector('#fullName');
    if (nameInput && nameInput.value.trim().length < 2) {
      showError(nameInput, 'Please provide your full name.');
      isValid = false;
    }

    // Validate Email
    const emailInput = form.querySelector('#workEmail');
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (emailInput && !emailRegex.test(emailInput.value.trim())) {
      showError(emailInput, 'Please provide a valid email address.');
      isValid = false;
    }

    // Validate Brand/Handle
    const companyInput = form.querySelector('#companyName');
    if (companyInput && companyInput.value.trim().length < 2) {
      showError(companyInput, 'Please provide your brand or social handle.');
      isValid = false;
    }

    // Validate Message
    const msgInput = form.querySelector('#messageInput');
    if (msgInput && msgInput.value.trim().length < 15) {
      showError(msgInput, 'Please share at least 15 characters of project context.');
      isValid = false;
    }

    if (!isValid) return;

    // Simulated working submission with UI feedback
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = 'Submitting Inquiry...';

    setTimeout(() => {
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;
      form.reset();

      if (statusAlert) {
        statusAlert.classList.add('success');
        statusAlert.style.display = 'block';
        statusAlert.innerHTML = '<strong>Inquiry Received.</strong> Thank you for connecting with MY UGC VIDEO. Our creative directors review every brief and will respond within 24 business hours.';
        statusAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    }, 700);
  });

  function showError(inputElement, message) {
    inputElement.classList.add('error');
    const errorMsg = inputElement.parentElement.querySelector('.form-error-msg');
    if (errorMsg) {
      errorMsg.textContent = message;
      errorMsg.classList.add('visible');
    }
  }
}

/* --------------------------------------------------------------------------
   7. NEWSLETTER SUBSCRIPTION FORM
   -------------------------------------------------------------------------- */
function initNewsletterForms() {
  const forms = document.querySelectorAll('.newsletter-form');
  if (!forms.length) return;

  forms.forEach(form => {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const input = form.querySelector('.newsletter-input');
      const feedback = form.querySelector('.newsletter-feedback');
      if (!input) return;

      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      const val = input.value.trim();

      if (!emailRegex.test(val)) {
        if (feedback) {
          feedback.textContent = 'Please enter a valid email address.';
          feedback.className = 'newsletter-feedback error';
        }
        return;
      }

      // Valid subscription feedback
      if (feedback) {
        feedback.textContent = 'Thank you for subscribing to MY UGC VIDEO dispatches.';
        feedback.className = 'newsletter-feedback success';
      }
      input.value = '';
    });
  });
}

/* --------------------------------------------------------------------------
   8. ACTIVE NAVIGATION LINK DETECTION
   -------------------------------------------------------------------------- */
function setActiveNavLinks() {
  const currentPath = window.location.pathname.toLowerCase();
  const navLinks = document.querySelectorAll('.nav-link, .mobile-nav-link');

  navLinks.forEach(link => {
    const href = link.getAttribute('href');
    if (!href) return;

    const linkPath = href.toLowerCase();

    // Check if on homepage
    if ((currentPath.endsWith('/') || currentPath.endsWith('index.html') || currentPath === '') &&
        (linkPath === 'index.html' || linkPath === './' || linkPath === '/')) {
      link.classList.add('active');
    } else if (linkPath !== 'index.html' && linkPath !== './' && currentPath.includes(linkPath.replace('./', '').replace('/', ''))) {
      link.classList.add('active');
    } else {
      link.classList.remove('active');
    }
  });
}

/**
 * Interactive Services Directory Slide/Open Interaction (services.html)
 */
function initInteractiveServicesDirectory() {
  const cards = document.querySelectorAll('.service-interactive-card');
  if (!cards.length) return;

  cards.forEach((card, index) => {
    const trigger = card.querySelector('.service-interactive-header');
    const panel = card.querySelector('.service-interactive-panel');
    if (!trigger || !panel) return;

    const panelId = panel.id || ('service-panel-' + (index + 1));
    panel.id = panelId;
    trigger.setAttribute('aria-controls', panelId);
    trigger.setAttribute('aria-expanded', 'false');

    trigger.addEventListener('click', (e) => {
      // If user clicked directly on a link inside the header, allow navigation
      if (e.target.closest('a')) return;
      e.preventDefault();

      const isOpen = card.classList.contains('active');

      // Close other cards in directory
      cards.forEach(otherCard => {
        if (otherCard !== card && otherCard.classList.contains('active')) {
          otherCard.classList.remove('active');
          const otherTrigger = otherCard.querySelector('.service-interactive-header');
          const otherPanel = otherCard.querySelector('.service-interactive-panel');
          if (otherTrigger) otherTrigger.setAttribute('aria-expanded', 'false');
          if (otherPanel) otherPanel.style.maxHeight = null;
        }
      });

      if (isOpen) {
        card.classList.remove('active');
        trigger.setAttribute('aria-expanded', 'false');
        panel.style.maxHeight = null;
      } else {
        card.classList.add('active');
        trigger.setAttribute('aria-expanded', 'true');
        panel.style.maxHeight = panel.scrollHeight + 'px';
      }
    });
  });

  window.addEventListener('resize', () => {
    document.querySelectorAll('.service-interactive-card.active').forEach(activeCard => {
      const activePanel = activeCard.querySelector('.service-interactive-panel');
      if (activePanel) {
        activePanel.style.maxHeight = activePanel.scrollHeight + 'px';
      }
    });
  });
}

/* --------------------------------------------------------------------------
   9. INTERACTIVE UGC SERVICES SLIDER (Homepage & Services)
   -------------------------------------------------------------------------- */
function initServicesSlider() {
  const viewport = document.querySelector('.services-slider-viewport');
  const track = document.querySelector('.services-slider-track');
  const prevBtn = document.querySelector('#sliderPrev');
  const nextBtn = document.querySelector('#sliderNext');
  const dotsContainer = document.querySelector('#sliderDots');

  if (!viewport || !track) return;

  const cards = track.querySelectorAll('.services-slider-card');
  if (!cards.length) return;

  let currentIndex = 0;
  let isDragging = false;
  let startX = 0;
  let currentTranslate = 0;
  let prevTranslate = 0;
  let animationID = 0;

  function getCardsPerView() {
    const width = window.innerWidth;
    if (width <= 700) return 1;
    if (width <= 1100) return 2;
    return 3;
  }

  function getMaxIndex() {
    return Math.max(0, cards.length - getCardsPerView());
  }

  function buildDots() {
    if (!dotsContainer) return;
    dotsContainer.innerHTML = '';
    const maxIdx = getMaxIndex();
    for (let i = 0; i <= maxIdx; i++) {
      const dot = document.createElement('button');
      dot.className = 'slider-dot' + (i === currentIndex ? ' active' : '');
      dot.setAttribute('aria-label', 'Go to slide ' + (i + 1));
      dot.addEventListener('click', () => {
        currentIndex = i;
        updateSliderPosition();
      });
      dotsContainer.appendChild(dot);
    }
  }

  function updateSliderPosition() {
    const maxIdx = getMaxIndex();
    if (currentIndex > maxIdx) currentIndex = maxIdx;
    if (currentIndex < 0) currentIndex = 0;

    const cardWidth = cards[0].getBoundingClientRect().width;
    const gap = 24; // 1.5rem
    const moveAmount = currentIndex * (cardWidth + gap);

    track.style.transform = 'translateX(-' + moveAmount + 'px)';

    if (prevBtn) prevBtn.disabled = currentIndex === 0;
    if (nextBtn) nextBtn.disabled = currentIndex >= maxIdx;

    if (dotsContainer) {
      const dots = dotsContainer.querySelectorAll('.slider-dot');
      dots.forEach((dot, idx) => {
        dot.classList.toggle('active', idx === currentIndex);
      });
    }
  }

  if (prevBtn) {
    prevBtn.addEventListener('click', () => {
      if (currentIndex > 0) {
        currentIndex--;
        updateSliderPosition();
      }
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', () => {
      const maxIdx = getMaxIndex();
      if (currentIndex < maxIdx) {
        currentIndex++;
        updateSliderPosition();
      }
    });
  }

  // Keyboard accessibility
  viewport.setAttribute('tabindex', '0');
  viewport.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') {
      e.preventDefault();
      if (currentIndex > 0) {
        currentIndex--;
        updateSliderPosition();
      }
    } else if (e.key === 'ArrowRight') {
      e.preventDefault();
      if (currentIndex < getMaxIndex()) {
        currentIndex++;
        updateSliderPosition();
      }
    }
  });

  // Touch and Drag gestures
  viewport.addEventListener('touchstart', touchStart, { passive: true });
  viewport.addEventListener('touchend', touchEnd);
  viewport.addEventListener('touchmove', touchMove, { passive: true });

  viewport.addEventListener('mousedown', dragStart);
  viewport.addEventListener('mouseup', dragEnd);
  viewport.addEventListener('mouseleave', dragEnd);
  viewport.addEventListener('mousemove', dragMove);

  function touchStart(event) {
    startX = event.touches[0].clientX;
    isDragging = true;
  }

  function touchMove(event) {
    if (!isDragging) return;
    const currentX = event.touches[0].clientX;
    const diff = currentX - startX;
    if (Math.abs(diff) > 40) {
      if (diff < 0 && currentIndex < getMaxIndex()) {
        currentIndex++;
        updateSliderPosition();
        isDragging = false;
      } else if (diff > 0 && currentIndex > 0) {
        currentIndex--;
        updateSliderPosition();
        isDragging = false;
      }
    }
  }

  function touchEnd() {
    isDragging = false;
  }

  function dragStart(event) {
    startX = event.clientX;
    isDragging = true;
    viewport.classList.add('is-dragging');
  }

  function dragMove(event) {
    if (!isDragging) return;
    const diff = event.clientX - startX;
    if (Math.abs(diff) > 50) {
      if (diff < 0 && currentIndex < getMaxIndex()) {
        currentIndex++;
        updateSliderPosition();
        isDragging = false;
      } else if (diff > 0 && currentIndex > 0) {
        currentIndex--;
        updateSliderPosition();
        isDragging = false;
      }
    }
  }

  function dragEnd() {
    isDragging = false;
    viewport.classList.remove('is-dragging');
  }

  window.addEventListener('resize', () => {
    buildDots();
    updateSliderPosition();
  });

  buildDots();
  updateSliderPosition();
}

/* --------------------------------------------------------------------------
   10. DUAL INQUIRY TABS & VALIDATION (General Contact + Detailed Project)
   -------------------------------------------------------------------------- */
function initDualInquiryForms() {
  const tabs = document.querySelectorAll('.inquiry-tab-trigger');
  const panels = document.querySelectorAll('.inquiry-tab-content');

  if (tabs.length && panels.length) {
    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        const targetId = tab.getAttribute('data-target');
        tabs.forEach(t => t.classList.remove('active'));
        panels.forEach(p => p.classList.remove('active'));

        tab.classList.add('active');
        const targetPanel = document.getElementById(targetId);
        if (targetPanel) targetPanel.classList.add('active');
      });
    });
  }

  // Detailed Project Inquiry Form
  const detailedForm = document.querySelector('#detailedInquiryForm');
  if (detailedForm) {
    const alertBox = detailedForm.querySelector('.form-status-alert');

    detailedForm.addEventListener('submit', (e) => {
      e.preventDefault();
      let isValid = true;

      detailedForm.querySelectorAll('.form-control').forEach(ctrl => ctrl.classList.remove('error'));
      detailedForm.querySelectorAll('.form-error-msg').forEach(msg => msg.classList.remove('visible'));

      const name = detailedForm.querySelector('#detailedName');
      if (name && name.value.trim().length < 2) {
        showError(name, 'Please provide your contact name.');
        isValid = false;
      }

      const email = detailedForm.querySelector('#detailedEmail');
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (email && !emailRegex.test(email.value.trim())) {
        showError(email, 'Please enter a valid business email.');
        isValid = false;
      }

      const brand = detailedForm.querySelector('#detailedBrand');
      if (brand && brand.value.trim().length < 2) {
        showError(brand, 'Please enter your company or brand name.');
        isValid = false;
      }

      const details = detailedForm.querySelector('#detailedRequirements');
      if (details && details.value.trim().length < 15) {
        showError(details, 'Please share at least 15 characters of project context.');
        isValid = false;
      }

      if (!isValid) return;

      const submitBtn = detailedForm.querySelector('button[type="submit"]');
      const origText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'Submitting Scope...';

      setTimeout(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = origText;
        detailedForm.reset();

        if (alertBox) {
          alertBox.className = 'form-status-alert success';
          alertBox.style.display = 'block';
          alertBox.innerHTML = '<strong>Project Scope Received.</strong> Our executive production team will review your creative specifications and schedule a discovery call within 24 business hours.';
          alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      }, 750);
    });
  }

  function showError(inputElement, message) {
    inputElement.classList.add('error');
    const errorMsg = inputElement.parentElement.querySelector('.form-error-msg');
    if (errorMsg) {
      errorMsg.textContent = message;
      errorMsg.classList.add('visible');
    }
  }
}

/* --------------------------------------------------------------------------
   11. UGC VIDEO CATEGORIES TAB FILTERING (Homepage Section 7)
   -------------------------------------------------------------------------- */
function initUgcCategoryTabs() {
  const tabBtns = document.querySelectorAll('.ugc-tab-btn');
  const exampleCards = document.querySelectorAll('.ugc-example-card');

  if (!tabBtns.length || !exampleCards.length) return;

  tabBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      tabBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');

      const filter = btn.getAttribute('data-category');

      exampleCards.forEach(card => {
        const cat = card.getAttribute('data-category');
        if (filter === 'all' || filter === cat) {
          card.style.display = 'block';
          card.style.opacity = '0';
          setTimeout(() => { card.style.opacity = '1'; }, 30);
        } else {
          card.style.display = 'none';
        }
      });
    });
  });
}

/* --------------------------------------------------------------------------
   12. VIDEO PREVIEW MODAL PLAYER
   -------------------------------------------------------------------------- */
function initVideoModal() {
  const modal = document.querySelector('#videoModal');
  const triggers = document.querySelectorAll('.video-play-trigger');
  if (!modal || !triggers.length) return;

  const closeBtn = modal.querySelector('.video-modal-close-btn');
  const modalImg = modal.querySelector('#videoModalImg');
  const modalTitle = modal.querySelector('#videoModalTitle');
  const modalSub = modal.querySelector('#videoModalSub');

  const openModal = (imgSrc, title, sub) => {
    if (modalImg) modalImg.src = imgSrc;
    if (modalTitle) modalTitle.textContent = title;
    if (modalSub) modalSub.textContent = sub;
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
  };

  const closeModal = () => {
    modal.classList.remove('active');
    document.body.style.overflow = '';
  };

  triggers.forEach(trig => {
    trig.addEventListener('click', (e) => {
      e.preventDefault();
      const imgSrc = trig.getAttribute('data-video-poster') || 'images/hero/hero-ugc-production.jpg';
      const title = trig.getAttribute('data-video-title') || 'Featured UGC Creator Showcase';
      const sub = trig.getAttribute('data-video-meta') || '9:16 Vertical Creator Video • High Conversion Direct-Response';
      openModal(imgSrc, title, sub);
    });
  });

  if (closeBtn) closeBtn.addEventListener('click', closeModal);

  modal.addEventListener('click', (e) => {
    if (e.target === modal) closeModal();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal.classList.contains('active')) {
      closeModal();
    }
  });
}

/* --------------------------------------------------------------------------
   13. HOMEPAGE PORTFOLIO CATEGORY FILTER TABS
   -------------------------------------------------------------------------- */
function initHomepagePortfolioFilter() {
  const filterBtns = document.querySelectorAll('.portfolio-filter-btn');
  const projectCards = document.querySelectorAll('.portfolio-project-card');

  if (!filterBtns.length || !projectCards.length) return;

  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      const selectedFilter = btn.getAttribute('data-filter') || 'all';

      // Update active state on buttons
      filterBtns.forEach(b => {
        b.classList.remove('active');
        b.setAttribute('aria-selected', 'false');
      });
      btn.classList.add('active');
      btn.setAttribute('aria-selected', 'true');

      // Filter project cards with smooth fade
      projectCards.forEach(card => {
        const cardCategory = card.getAttribute('data-category');
        if (selectedFilter === 'all' || cardCategory === selectedFilter) {
          card.style.display = 'block';
          card.style.opacity = '0';
          card.style.transform = 'translateY(12px)';
          setTimeout(() => {
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
          }, 40);
        } else {
          card.style.display = 'none';
        }
      });
    });
  });
}

/* --------------------------------------------------------------------------
   14. INTERACTIVE TESTIMONIAL SLIDER (WITH AUTOPLAY, TOUCH SWIPE & CONTROLS)
   -------------------------------------------------------------------------- */
function initTestimonialSlider() {
  const slider = document.querySelector('#testimonialSlider');
  const track = document.querySelector('#testimonialTrack');
  const prevBtn = document.querySelector('#testimonialPrevBtn');
  const nextBtn = document.querySelector('#testimonialNextBtn');
  const dots = document.querySelectorAll('.testimonial-dot');
  const slides = document.querySelectorAll('.testimonial-slide');

  if (!track || !slides.length) return;

  let currentSlide = 0;
  const totalSlides = slides.length;
  let autoplayTimer = null;

  const updateSlidePosition = (index) => {
    currentSlide = (index + totalSlides) % totalSlides;
    track.style.transform = `translateX(-${currentSlide * 100}%)`;

    slides.forEach((slide, idx) => {
      if (idx === currentSlide) {
        slide.classList.add('active');
      } else {
        slide.classList.remove('active');
      }
    });

    dots.forEach((dot, idx) => {
      if (idx === currentSlide) {
        dot.classList.add('active');
        dot.setAttribute('aria-selected', 'true');
      } else {
        dot.classList.remove('active');
        dot.setAttribute('aria-selected', 'false');
      }
    });
  };

  const nextSlide = () => updateSlidePosition(currentSlide + 1);
  const prevSlide = () => updateSlidePosition(currentSlide - 1);

  if (nextBtn) nextBtn.addEventListener('click', nextSlide);
  if (prevBtn) prevBtn.addEventListener('click', prevSlide);

  dots.forEach(dot => {
    dot.addEventListener('click', () => {
      const slideIdx = parseInt(dot.getAttribute('data-slide'), 10);
      if (!isNaN(slideIdx)) updateSlidePosition(slideIdx);
    });
  });

  // Touch Swipe Support
  let startX = 0;
  let endX = 0;

  track.addEventListener('touchstart', (e) => {
    startX = e.touches[0].clientX;
  }, { passive: true });

  track.addEventListener('touchend', (e) => {
    endX = e.changedTouches[0].clientX;
    const diff = startX - endX;
    if (Math.abs(diff) > 40) {
      if (diff > 0) nextSlide();
      else prevSlide();
    }
  }, { passive: true });

  // Autoplay with pause on hover
  const startAutoplay = () => {
    stopAutoplay();
    autoplayTimer = setInterval(nextSlide, 7000);
  };

  const stopAutoplay = () => {
    if (autoplayTimer) {
      clearInterval(autoplayTimer);
      autoplayTimer = null;
    }
  };

  if (slider) {
    slider.addEventListener('mouseenter', stopAutoplay);
    slider.addEventListener('mouseleave', startAutoplay);
  }

  startAutoplay();
}

/* --------------------------------------------------------------------------
   15. HOMEPAGE IN-PAGE PROJECT INQUIRY FORM
   -------------------------------------------------------------------------- */
function initHomepageInquiryForm() {
  const form = document.querySelector('#projectInquiryForm');
  const alertBox = document.querySelector('#inquiryStatusAlert');
  if (!form) return;

  const nameInput = form.querySelector('#inquiryName');
  const emailInput = form.querySelector('#inquiryEmail');
  const brandInput = form.querySelector('#inquiryBrand');
  const serviceInput = form.querySelector('#inquiryService');
  const detailsInput = form.querySelector('#inquiryDetails');
  const submitBtn = form.querySelector('.inquiry-submit-btn');

  const setFieldError = (input, message) => {
    if (!input) return;
    input.classList.add('input-error');
    const parent = input.closest('.form-group');
    if (parent) {
      const err = parent.querySelector('.form-error-msg');
      if (err) err.textContent = message;
    }
  };

  const clearFieldError = (input) => {
    if (!input) return;
    input.classList.remove('input-error');
    const parent = input.closest('.form-group');
    if (parent) {
      const err = parent.querySelector('.form-error-msg');
      if (err) err.textContent = '';
    }
  };

  // Clear error on input
  [nameInput, emailInput, brandInput, serviceInput, detailsInput].forEach(inp => {
    if (!inp) return;
    inp.addEventListener('input', () => clearFieldError(inp));
    inp.addEventListener('change', () => clearFieldError(inp));
  });

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    let hasError = false;

    // Reset alert
    if (alertBox) {
      alertBox.style.display = 'none';
      alertBox.className = 'inquiry-status-alert';
      alertBox.textContent = '';
    }

    // Name validation
    if (!nameInput.value.trim()) {
      setFieldError(nameInput, 'Please enter your name.');
      hasError = true;
    }

    // Email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailInput.value.trim() || !emailRegex.test(emailInput.value.trim())) {
      setFieldError(emailInput, 'Please enter a valid work email address.');
      hasError = true;
    }

    // Brand validation
    if (!brandInput.value.trim()) {
      setFieldError(brandInput, 'Please provide your brand name or website.');
      hasError = true;
    }

    // Service validation
    if (!serviceInput.value) {
      setFieldError(serviceInput, 'Please select your primary content need.');
      hasError = true;
    }

    // Details validation
    if (!detailsInput.value.trim() || detailsInput.value.trim().length < 15) {
      setFieldError(detailsInput, 'Please share at least a brief summary of your project goals (min 15 characters).');
      hasError = true;
    }

    if (hasError) {
      if (alertBox) {
        alertBox.textContent = 'Please complete the highlighted required fields before submitting.';
        alertBox.classList.add('error');
        alertBox.style.display = 'block';
      }
      return;
    }

    // Form submission state
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = `<span>SUBMITTING INQUIRY...</span>`;
    }

    // Simulate instant secure submission
    setTimeout(() => {
      form.reset();
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = `<span>SEND PROJECT INQUIRY</span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>`;
      }
      if (alertBox) {
        alertBox.innerHTML = `<strong>Thank you! Your project inquiry has been received.</strong><br>Our creative director will review your requirements and reach out within 24 business hours with custom creator recommendations and a tailored quote.`;
        alertBox.classList.add('success');
        alertBox.style.display = 'block';
      }
    }, 750);
  });
}
