/**
 * MY UGC VIDEO — Vanilla JavaScript Interactions
 * Handles Header Scroll, Desktop/Mobile Hover & Click Dropdowns,
 * Mobile Drawer, FAQ Accordion, Blog Category Filtering, Load More,
 * Contact Form Validation, and Newsletter Submissions.
 */

function initAllModules() {
  const modules = [
    updateHeaderHeight,
    initStickyHeader,
    initMobileNav,
    initMobileDropdown,
    initFaqAccordion,
    initBlogFilters,
    initContactForm,
    initNewsletterForms,
    setActiveNavLinks,
    initInteractiveServicesDirectory,
    initServicesSlider,
    initDualInquiryForms,
    initVideoModal,
    initUgcCategoryTabs,
    init3DVideoCarousel,
    initHomepageHeroForm,
    initHomepageInquiryForm,
    initBlogTableOfContents,
    initBlogStrategyCallForm,
    initAnnouncementBar,
    initFloatingCtasAndChatbot,
    initContactPopup
  ];

  modules.forEach((fn) => {
    try {
      if (typeof fn === 'function') fn();
    } catch (err) {
      console.warn('[MY UGC VIDEO] Module init warning:', err);
    }
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAllModules);
} else {
  initAllModules();
}

/* --------------------------------------------------------------------------
   0. REAL-TIME RESPONSIVE HEADER HEIGHT SYNC
   Measures exact header wrapper height (including contact strip & announcement
   bar on all viewports) and publishes --site-header-height to CSS :root.
   -------------------------------------------------------------------------- */
function updateHeaderHeight() {
  const wrapper = document.querySelector('.site-header-wrapper') || document.querySelector('.site-header');
  if (wrapper) {
    const h = Math.ceil(wrapper.offsetHeight || wrapper.getBoundingClientRect().height);
    if (h > 0) {
      document.documentElement.style.setProperty('--site-header-height', `${h}px`);
    }
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', updateHeaderHeight);
} else {
  updateHeaderHeight();
}
window.addEventListener('load', updateHeaderHeight);
window.addEventListener('resize', updateHeaderHeight, { passive: true });

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
   2. MOBILE NAVIGATION DRAWER & FULL INTERACTION CONTROLLER
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
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    document.documentElement.style.overflow = 'hidden';
  };

  const closeDrawer = () => {
    toggleBtn.setAttribute('aria-expanded', 'false');
    drawer.classList.remove('active');
    overlay.classList.remove('active');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    document.documentElement.style.overflow = '';
  };

  // Toggle button click handler
  toggleBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    const isExpanded = toggleBtn.getAttribute('aria-expanded') === 'true';
    if (isExpanded) {
      closeDrawer();
    } else {
      openDrawer();
    }
  });

  // Dedicated Close Button (X) inside mobile drawer header
  const closeBtns = drawer.querySelectorAll('.drawer-close-btn');
  closeBtns.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      closeDrawer();
    });
  });

  // Overlay click to close
  overlay.addEventListener('click', closeDrawer);

  // Click outside drawer to close
  document.addEventListener('click', (e) => {
    if (drawer.classList.contains('active')) {
      if (!drawer.contains(e.target) && !toggleBtn.contains(e.target)) {
        closeDrawer();
      }
    }
  });

  // Close when clicking nav links inside drawer (except dropdown toggle)
  const drawerLinks = drawer.querySelectorAll('a:not(.mobile-dropdown-toggle)');
  drawerLinks.forEach(link => {
    link.addEventListener('click', () => {
      // Small timeout so anchor jump / navigation happens cleanly
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
  const arrowButtons = document.querySelectorAll('.mobile-dropdown-arrow-btn');

  if (arrowButtons.length > 0) {
    arrowButtons.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        const wrap = btn.closest('.mobile-dropdown-wrap');
        if (!wrap) return;
        const dropdownContent = wrap.querySelector('.mobile-dropdown-content');
        if (!dropdownContent) return;

        const isExpanded = btn.getAttribute('aria-expanded') === 'true';
        const nextState = !isExpanded;

        btn.setAttribute('aria-expanded', nextState ? 'true' : 'false');
        dropdownContent.classList.toggle('open', nextState);
        const arrow = btn.querySelector('.dropdown-arrow');
        if (arrow) {
          arrow.style.transform = nextState ? 'rotate(180deg)' : 'rotate(0deg)';
        }
      });
    });
    return;
  }

  // Fallback for any legacy markup
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
/**
 * Helper to dynamically determine the API endpoint for leads submission
 * Handles both local subfolder (e.g. /UGC%20Website/api/contact or /UGC Website/api/contact) and production (/api/contact)
 */
function getLeadApiEndpoint() {
  try {
    const cleanPath = decodeURIComponent(window.location.pathname || '');
    if (cleanPath.toLowerCase().startsWith('/ugc website')) {
      return '/UGC%20Website/api/contact';
    }
  } catch (e) {
    // Fallback if decodeURIComponent fails
    if ((window.location.pathname || '').toLowerCase().includes('ugc')) {
      return '/UGC%20Website/api/contact';
    }
  }
  return '/api/contact';
}

/**
 * Cached CSRF token for asynchronous lead submissions
 */
let cachedLeadCsrfToken = null;

async function getLeadCsrfToken() {
  if (cachedLeadCsrfToken) return cachedLeadCsrfToken;
  try {
    const res = await fetch(getLeadApiEndpoint(), {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin'
    });
    if (res.ok) {
      const data = await res.json();
      if (data && data.csrf_token) {
        cachedLeadCsrfToken = data.csrf_token;
        return cachedLeadCsrfToken;
      }
    }
  } catch (err) {
    console.warn('Could not fetch initial CSRF token:', err);
  }
  return '';
}

// Prefetch CSRF token early when DOM loads
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    getLeadCsrfToken();
  });
} else {
  getLeadCsrfToken();
}

function initContactForm() {
  const form = document.querySelector('#contactForm');
  if (!form) return;

  const statusAlert = document.querySelector('#formStatusAlert');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    let isValid = true;

    // Reset previous errors
    form.querySelectorAll('.form-control').forEach(ctrl => ctrl.classList.remove('error'));
    form.querySelectorAll('.form-error-msg').forEach(msg => {
      msg.classList.remove('visible');
      msg.textContent = '';
    });
    if (statusAlert) {
      statusAlert.className = 'form-status-alert';
      statusAlert.style.display = 'none';
      statusAlert.textContent = '';
    }

    // 1. Validate Full Name (Required)
    const nameInput = form.querySelector('#fullName');
    if (nameInput && nameInput.value.trim().length < 2) {
      showError(nameInput, 'Please provide your full name.');
      isValid = false;
    }

    // 2. Validate Email Address (Required)
    const emailInput = form.querySelector('#workEmail');
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (emailInput && !emailRegex.test(emailInput.value.trim())) {
      showError(emailInput, 'Please provide a valid email address.');
      isValid = false;
    }

    // 3. Validate Phone Number (Required)
    const phoneInput = form.querySelector('#phoneNumber');
    const phoneVal = phoneInput ? phoneInput.value.trim() : '';
    const phoneRegex = /^[+]?[\d\s\-().]{7,25}$/;
    if (!phoneInput || phoneVal.length < 7 || !phoneRegex.test(phoneVal)) {
      showError(phoneInput, 'Please provide a valid phone number (at least 7 digits).');
      isValid = false;
    }

    // 4. Message is Optional
    const msgInput = form.querySelector('#messageInput');
    if (msgInput && msgInput.value.trim().length > 5000) {
      showError(msgInput, 'Message is too long (maximum 5000 characters).');
      isValid = false;
    }

    if (!isValid) return;

    // Working submission with UI feedback
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = 'Sending Inquiry...';

    try {
      // Prepare Form Data for centralized endpoint
      const csrfToken = await getLeadCsrfToken();
      const formData = new FormData(form);
      formData.set('source', 'Contact Page Form');
      formData.set('csrf_token', csrfToken);

      // Map field IDs to standardized API keys
      if (nameInput) formData.set('name', nameInput.value.trim());
      if (emailInput) formData.set('email', emailInput.value.trim());
      if (phoneInput) formData.set('phone', phoneInput.value.trim());
      formData.set('message', msgInput && msgInput.value.trim() ? msgInput.value.trim() : '');

      const response = await fetch(getLeadApiEndpoint(), {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        },
        credentials: 'same-origin',
        body: formData
      });

      const result = await response.json();

      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;

      if (result.success) {
        form.reset();
        if (statusAlert) {
          statusAlert.className = 'form-status-alert success';
          statusAlert.style.display = 'block';
          const emailNote = result.email_sent ? ' A confirmation email has been sent to your address.' : '';
          statusAlert.innerHTML = `<strong>Thank you! Your enquiry has been received successfully.</strong> We'll get back to you soon.${emailNote}`;
          statusAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      } else {
        if (statusAlert) {
          statusAlert.className = 'form-status-alert error';
          statusAlert.style.display = 'block';
          const msg = result.message || 'We could not submit your inquiry at this moment. Please check your inputs and try again.';
          statusAlert.innerHTML = `<strong>Submission Error:</strong> ${msg}`;
          statusAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      }
    } catch (err) {
      console.error('Lead submission network error:', err);
      submitBtn.disabled = false;
      submitBtn.innerHTML = originalText;
      if (statusAlert) {
        statusAlert.className = 'form-status-alert error';
        statusAlert.style.display = 'block';
        statusAlert.innerHTML = '<strong>Network Error:</strong> Unable to connect to the server. Please check your internet connection or email us directly.';
      }
    }
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

    detailedForm.addEventListener('submit', async (e) => {
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

      try {
        const csrfToken = await getLeadCsrfToken();
        const formData = new FormData(detailedForm);
        formData.set('source', 'Contact Page Form');
        formData.set('csrf_token', csrfToken);

        if (name) formData.set('name', name.value.trim());
        if (email) formData.set('email', email.value.trim());
        if (brand) formData.set('brand', brand.value.trim());
        const phone = detailedForm.querySelector('#detailedPhone');
        if (phone) formData.set('phone', phone.value.trim());
        const service = detailedForm.querySelector('#detailedService');
        if (service) formData.set('service', service.value);
        const volume = detailedForm.querySelector('#detailedVolume');
        if (volume) formData.set('budget', volume.value);
        if (details) formData.set('message', details.value.trim());

        const response = await fetch(getLeadApiEndpoint(), {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
          },
          body: formData
        });

        const result = await response.json();

        submitBtn.disabled = false;
        submitBtn.innerHTML = origText;

        if (result.success) {
          detailedForm.reset();
          if (alertBox) {
            alertBox.className = 'form-status-alert success';
            alertBox.style.display = 'block';
            const emailNote = result.email_sent ? ' A confirmation email has been sent to your address.' : '';
            alertBox.innerHTML = `<strong>Thank you! Your enquiry has been received successfully.</strong> We'll get back to you soon.${emailNote}`;
            alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          }
        } else {
          if (alertBox) {
            alertBox.className = 'form-status-alert error';
            alertBox.style.display = 'block';
            const msg = result.message || 'We could not submit your project scope at this time. Please check your inputs and try again.';
            alertBox.innerHTML = `<strong>Submission Error:</strong> ${msg}`;
            alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
          }
        }
      } catch (err) {
        console.error('Detailed inquiry submission error:', err);
        submitBtn.disabled = false;
        submitBtn.innerHTML = origText;
        if (alertBox) {
          alertBox.className = 'form-status-alert error';
          alertBox.style.display = 'block';
          alertBox.innerHTML = '<strong>Network Error:</strong> Unable to connect to the server. Please check your connection or email us directly.';
        }
      }
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
   13. 3D-STYLE VERTICAL VIDEO CAROUSEL (PORTFOLIO SECTION)
   -------------------------------------------------------------------------- */
function init3DVideoCarousel() {
  const carousel = document.querySelector('#portfolioCarousel');
  const stage = document.querySelector('#portfolioStage');
  const cards = Array.from(document.querySelectorAll('.portfolio-3d-card'));
  const prevBtn = document.querySelector('.portfolio-carousel-prev');
  const nextBtn = document.querySelector('.portfolio-carousel-next');
  const dots = Array.from(document.querySelectorAll('.portfolio-carousel-dot'));
  const filterBtns = Array.from(document.querySelectorAll('.portfolio-filter-btn'));

  if (!carousel || !cards.length) return;

  const totalCards = cards.length;
  let currentIndex = 0;
  let isAnimating = false;
  const ANIMATION_DURATION = 400; // ms matching CSS transition
  const AUTOPLAY_INTERVAL = 3500; // 3.5s automatic slide transition
  let autoplayTimer = null;
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  // Autoplay control functions
  const startAutoplay = () => {
    if (prefersReducedMotion && prefersReducedMotion.matches) return;
    stopAutoplay();
    autoplayTimer = setInterval(() => {
      updateCarousel(currentIndex + 1);
    }, AUTOPLAY_INTERVAL);
  };

  const stopAutoplay = () => {
    if (autoplayTimer) {
      clearInterval(autoplayTimer);
      autoplayTimer = null;
    }
  };

  const resetAutoplay = () => {
    stopAutoplay();
    startAutoplay();
  };

  // Category filter mapping to card index
  const categoryIndexMap = {
    'all': 0,
    'wellness': 0,
    'skincare': 1,
    'beverage': 2,
    'technology': 3,
    'lifestyle': 4,
    'beauty': 5
  };

  const updateCarousel = (newIndex, immediate = false) => {
    // Prevent rapid repeated clicks from breaking the animation
    if (!immediate && isAnimating) return;

    if (!immediate) {
      isAnimating = true;
      setTimeout(() => {
        isAnimating = false;
      }, ANIMATION_DURATION);
    }

    // Circular index calculation: 0 <= newIndex < totalCards
    newIndex = ((newIndex % totalCards) + totalCards) % totalCards;

    // Pause old center video & remove playing class
    const oldCenterCard = cards[currentIndex];
    if (oldCenterCard && oldCenterCard !== cards[newIndex]) {
      const oldVideo = oldCenterCard.querySelector('video');
      if (oldVideo && !oldVideo.paused) {
        oldVideo.pause();
      }
      oldCenterCard.classList.remove('is-playing');
    }

    currentIndex = newIndex;

    // Update 3D card position classes
    cards.forEach((card, idx) => {
      card.classList.remove(
        'pos-center',
        'pos-left',
        'pos-right',
        'pos-far-left',
        'pos-far-right',
        'pos-hidden'
      );

      const diff = (idx - currentIndex + totalCards) % totalCards;

      if (diff === 0) {
        card.classList.add('pos-center');
        card.setAttribute('aria-hidden', 'false');
      } else if (diff === totalCards - 1) {
        card.classList.add('pos-left');
        card.setAttribute('aria-hidden', 'true');
      } else if (diff === 1) {
        card.classList.add('pos-right');
        card.setAttribute('aria-hidden', 'true');
      } else if (diff === totalCards - 2) {
        card.classList.add('pos-far-left');
        card.setAttribute('aria-hidden', 'true');
      } else if (diff === 2) {
        card.classList.add('pos-far-right');
        card.setAttribute('aria-hidden', 'true');
      } else {
        card.classList.add('pos-hidden');
        card.setAttribute('aria-hidden', 'true');
      }

      // Ensure all non-center videos are strictly paused
      if (diff !== 0) {
        const v = card.querySelector('video');
        if (v && !v.paused) v.pause();
        card.classList.remove('is-playing');
      }
    });

    // Update Dots indicator
    dots.forEach((dot, idx) => {
      const isActive = idx === currentIndex;
      dot.classList.toggle('active', isActive);
      dot.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    // Update Category Filter Tabs active state (if present)
    if (filterBtns.length) {
      const currentCategory = cards[currentIndex].getAttribute('data-category');
      filterBtns.forEach(btn => {
        const f = btn.getAttribute('data-filter');
        const isMatch = (f === currentCategory) || (f === 'all' && currentIndex === 0);
        btn.classList.toggle('active', isMatch);
        btn.setAttribute('aria-selected', isMatch ? 'true' : 'false');
      });
    }

    // Autoplay center video muted if video source is available
    const newCenterCard = cards[currentIndex];
    if (newCenterCard) {
      const newVideo = newCenterCard.querySelector('video');
      if (newVideo && newVideo.src) {
        newVideo.muted = true;
        const playPromise = newVideo.play();
        if (playPromise !== undefined) {
          playPromise
            .then(() => {
              newCenterCard.classList.add('is-playing');
            })
            .catch(() => {
              newCenterCard.classList.remove('is-playing');
            });
        }
      }
    }
  };

  // Click & Playback Interaction on Cards
  cards.forEach((card, idx) => {
    card.addEventListener('click', (e) => {
      // Never trigger modal, popup, fullscreen or zoom
      e.preventDefault();
      e.stopPropagation();
      resetAutoplay();

      if (card.classList.contains('pos-center')) {
        // Center card click toggles play/pause inside card
        const video = card.querySelector('video');
        if (video && video.src) {
          if (video.paused) {
            video.muted = true;
            const p = video.play();
            if (p !== undefined) {
              p.then(() => {
                card.classList.add('is-playing');
              }).catch(() => {
                card.classList.toggle('is-playing');
              });
            } else {
              card.classList.add('is-playing');
            }
          } else {
            video.pause();
            card.classList.remove('is-playing');
          }
        } else {
          card.classList.toggle('is-playing');
        }
      } else if (card.classList.contains('pos-left')) {
        // Left side card click smoothly slides to center
        updateCarousel(currentIndex - 1);
      } else if (card.classList.contains('pos-right')) {
        // Right side card click smoothly slides to center
        updateCarousel(currentIndex + 1);
      } else {
        updateCarousel(idx);
      }
    });
  });

  // Left & Right Arrow Buttons
  if (prevBtn) {
    prevBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      resetAutoplay();
      updateCarousel(currentIndex - 1);
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      resetAutoplay();
      updateCarousel(currentIndex + 1);
    });
  }

  // Dots Pagination
  dots.forEach((dot, idx) => {
    dot.addEventListener('click', (e) => {
      e.preventDefault();
      resetAutoplay();
      updateCarousel(idx);
    });
  });

  // Category filter tabs navigation (if present)
  filterBtns.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      resetAutoplay();
      const filter = btn.getAttribute('data-filter') || 'all';
      if (categoryIndexMap[filter] !== undefined) {
        updateCarousel(categoryIndexMap[filter]);
      }
    });
  });

  // Keyboard navigation when carousel has focus
  carousel.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft') {
      e.preventDefault();
      resetAutoplay();
      updateCarousel(currentIndex - 1);
    } else if (e.key === 'ArrowRight') {
      e.preventDefault();
      resetAutoplay();
      updateCarousel(currentIndex + 1);
    }
  });

  // Touch Swipe for mobile devices
  if (stage) {
    let touchStartX = 0;
    let touchStartY = 0;
    let touchEndX = 0;
    let touchEndY = 0;

    stage.addEventListener('touchstart', (e) => {
      touchStartX = e.changedTouches[0].screenX;
      touchStartY = e.changedTouches[0].screenY;
    }, { passive: true });

    stage.addEventListener('touchend', (e) => {
      touchEndX = e.changedTouches[0].screenX;
      touchEndY = e.changedTouches[0].screenY;
      const diffX = touchEndX - touchStartX;
      const diffY = touchEndY - touchStartY;
      if (Math.abs(diffX) > Math.abs(diffY) && Math.abs(diffX) > 40) {
        resetAutoplay();
        if (diffX < 0) {
          updateCarousel(currentIndex + 1);
        } else {
          updateCarousel(currentIndex - 1);
        }
      }
    }, { passive: true });
  }

  // Pause autoplay on mouse enter / resume on mouse leave
  carousel.addEventListener('mouseenter', stopAutoplay);
  carousel.addEventListener('mouseleave', () => {
    if (!carousel.contains(document.activeElement)) {
      startAutoplay();
    }
  });

  // Pause autoplay on focus / resume on blur
  carousel.addEventListener('focusin', stopAutoplay);
  carousel.addEventListener('focusout', (e) => {
    if (!carousel.contains(e.relatedTarget) && !carousel.matches(':hover')) {
      startAutoplay();
    }
  });

  // Pause autoplay when browser tab is inactive / resume when active
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      stopAutoplay();
    } else {
      startAutoplay();
    }
  });

  // Listen for reduced motion preference changes
  if (prefersReducedMotion && prefersReducedMotion.addEventListener) {
    prefersReducedMotion.addEventListener('change', () => {
      if (prefersReducedMotion.matches) {
        stopAutoplay();
      } else {
        startAutoplay();
      }
    });
  }

  // Initial call: activate first card immediately without blocking animation
  updateCarousel(0, true);
  startAutoplay();
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
   14B. HOMEPAGE HERO LEAD GENERATION FORM
   -------------------------------------------------------------------------- */
function initHomepageHeroForm() {
  const form = document.querySelector('#homepageHeroForm');
  const alertBox = document.querySelector('#heroFormAlert');
  if (!form) return;

  const nameInput = form.querySelector('#heroLeadName');
  const emailInput = form.querySelector('#heroLeadEmail');
  const brandInput = form.querySelector('#heroLeadBrand');
  const serviceInput = form.querySelector('#heroLeadService');
  const messageInput = form.querySelector('#heroLeadMessage');
  const submitBtn = form.querySelector('#heroLeadSubmitBtn');

  const setFieldError = (input, message) => {
    if (!input) return;
    input.classList.add('input-error');
    const parent = input.closest('.hero-form-group');
    if (parent) {
      const err = parent.querySelector('.hero-field-error');
      if (err) err.textContent = message;
    }
  };

  const clearFieldError = (input) => {
    if (!input) return;
    input.classList.remove('input-error');
    const parent = input.closest('.hero-form-group');
    if (parent) {
      const err = parent.querySelector('.hero-field-error');
      if (err) err.textContent = '';
    }
  };

  // Clear errors on user input
  [nameInput, emailInput, brandInput, serviceInput, messageInput].forEach(inp => {
    if (!inp) return;
    inp.addEventListener('input', () => clearFieldError(inp));
    inp.addEventListener('change', () => clearFieldError(inp));
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    let hasError = false;
    let firstInvalidInput = null;

    // Reset alert
    if (alertBox) {
      alertBox.style.display = 'none';
      alertBox.className = 'hero-form-alert';
      alertBox.textContent = '';
    }

    // Name validation
    if (!nameInput || !nameInput.value.trim() || nameInput.value.trim().length < 2) {
      setFieldError(nameInput, 'Please enter your full name (at least 2 characters).');
      hasError = true;
      if (!firstInvalidInput) firstInvalidInput = nameInput;
    } else {
      clearFieldError(nameInput);
    }

    // Email validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailInput || !emailInput.value.trim() || !emailRegex.test(emailInput.value.trim())) {
      setFieldError(emailInput, 'Please enter a valid work email address.');
      hasError = true;
      if (!firstInvalidInput) firstInvalidInput = emailInput;
    } else {
      clearFieldError(emailInput);
    }

    // Brand validation
    if (!brandInput || !brandInput.value.trim() || brandInput.value.trim().length < 2) {
      setFieldError(brandInput, 'Please provide your brand or company name.');
      hasError = true;
      if (!firstInvalidInput) firstInvalidInput = brandInput;
    } else {
      clearFieldError(brandInput);
    }

    // Service validation
    if (!serviceInput || !serviceInput.value) {
      setFieldError(serviceInput, 'Please select what you are looking for.');
      hasError = true;
      if (!firstInvalidInput) firstInvalidInput = serviceInput;
    } else {
      clearFieldError(serviceInput);
    }

    // Message validation
    if (!messageInput || !messageInput.value.trim() || messageInput.value.trim().length < 10) {
      setFieldError(messageInput, 'Please provide at least 10 characters describing your project.');
      hasError = true;
      if (!firstInvalidInput) firstInvalidInput = messageInput;
    } else {
      clearFieldError(messageInput);
    }

    if (hasError) {
      if (alertBox) {
        alertBox.textContent = 'Please complete the highlighted required fields.';
        alertBox.className = 'hero-form-alert error';
        alertBox.style.display = 'block';
      }
      if (firstInvalidInput) firstInvalidInput.focus();
      return;
    }

    // Prevent duplicate clicks and show loading state
    const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = `<span>SUBMITTING INQUIRY...</span>`;
    }

    try {
      const csrfToken = await getLeadCsrfToken();
      const formData = new FormData(form);
      formData.set('source', 'Homepage Hero Form');
      formData.set('csrf_token', csrfToken);

      if (nameInput) formData.set('name', nameInput.value.trim());
      if (emailInput) formData.set('email', emailInput.value.trim());
      if (brandInput) formData.set('brand', brandInput.value.trim());
      if (serviceInput) formData.set('service', serviceInput.value);
      if (messageInput) formData.set('message', messageInput.value.trim());

      const response = await fetch(getLeadApiEndpoint(), {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        },
        credentials: 'same-origin',
        body: formData
      });

      const result = await response.json();

      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnHtml || `<span>LET'S DISCUSS YOUR PROJECT</span>`;
      }

      if (result.success) {
        form.reset();
        [nameInput, emailInput, brandInput, serviceInput, messageInput].forEach(clearFieldError);
        if (alertBox) {
          const emailNote = result.email_sent ? '<br><span style="font-size:0.8rem;opacity:0.9;">A confirmation email has been dispatched to your inbox.</span>' : '';
          alertBox.innerHTML = `<strong>Thank you! Your project enquiry has been received.</strong> Our team will review your brief and get back to you shortly.${emailNote}`;
          alertBox.className = 'hero-form-alert success';
          alertBox.style.display = 'block';
          alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      } else {
        if (alertBox) {
          const msg = result.message || 'Please check your inputs and try again.';
          alertBox.innerHTML = `<strong>Submission Error:</strong> ${msg}`;
          alertBox.className = 'hero-form-alert error';
          alertBox.style.display = 'block';
        }
      }
    } catch (err) {
      console.error('Homepage hero inquiry submission error:', err);
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnHtml || `<span>LET'S DISCUSS YOUR PROJECT</span>`;
      }
      if (alertBox) {
        alertBox.innerHTML = `<strong>Network Error:</strong> Unable to send your message. Please check your connection or contact hello@myugcvideo.in directly.`;
        alertBox.className = 'hero-form-alert error';
        alertBox.style.display = 'block';
      }
    }
  });
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
  const budgetInput = form.querySelector('#inquiryBudget');
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

  form.addEventListener('submit', async (e) => {
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
    const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = `<span>SUBMITTING INQUIRY...</span>`;
    }

    try {
      const csrfToken = await getLeadCsrfToken();
      const formData = new FormData(form);
      formData.set('source', 'Homepage Inquiry Form');
      formData.set('csrf_token', csrfToken);

      if (nameInput) formData.set('name', nameInput.value.trim());
      if (emailInput) formData.set('email', emailInput.value.trim());
      if (brandInput) formData.set('brand', brandInput.value.trim());
      if (serviceInput) formData.set('service', serviceInput.value);
      if (budgetInput) formData.set('budget', budgetInput.value);
      if (detailsInput) formData.set('message', detailsInput.value.trim());

      const response = await fetch(getLeadApiEndpoint(), {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        },
        credentials: 'same-origin',
        body: formData
      });

      const result = await response.json();

      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnHtml || `<span>SEND PROJECT INQUIRY</span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>`;
      }

      if (result.success) {
        form.reset();
        if (alertBox) {
          const emailNote = result.email_sent ? '<br>A confirmation email has been dispatched to your inbox.' : '';
          alertBox.innerHTML = `<strong>Thank you! Your enquiry has been received successfully.</strong> We'll get back to you soon.${emailNote}`;
          alertBox.className = 'inquiry-status-alert success';
          alertBox.style.display = 'block';
          alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      } else {
        if (alertBox) {
          const msg = result.message || 'Please check your inputs and try again.';
          alertBox.innerHTML = `<strong>Submission Error:</strong> ${msg}`;
          alertBox.className = 'inquiry-status-alert error';
          alertBox.style.display = 'block';
          alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      }
    } catch (err) {
      console.error('Homepage inquiry submission error:', err);
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnHtml || `<span>SEND PROJECT INQUIRY</span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>`;
      }
      if (alertBox) {
        alertBox.innerHTML = `<strong>Network Error:</strong> Unable to connect to the server. Please try again or reach out to us directly.`;
        alertBox.className = 'inquiry-status-alert error';
        alertBox.style.display = 'block';
      }
    }
  });
}

/* --------------------------------------------------------------------------
   16. DYNAMIC BLOG TABLE OF CONTENTS & ACTIVE SCROLL OBSERVER
   -------------------------------------------------------------------------- */
function initBlogTableOfContents() {
  const tocList = document.querySelector('#blogTocList');
  const mobileToc = document.querySelector('.mobile-toc-accordion');
  if (!tocList && !mobileToc) return;

  const proseHeadings = document.querySelectorAll('.prose h2[id], .prose h3[id]');
  if (!proseHeadings.length) return;

  // Smooth scroll click handler for both desktop and mobile TOC links
  const tocLinks = document.querySelectorAll('.blog-toc-list a[href^="#"], .mobile-toc-list a[href^="#"]');
  tocLinks.forEach(link => {
    link.addEventListener('click', (e) => {
      const targetId = link.getAttribute('href').slice(1);
      const targetEl = document.getElementById(targetId);
      if (targetEl) {
        e.preventDefault();
        targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        history.pushState(null, '', '#' + targetId);

        // Auto close mobile accordion on link click
        if (mobileToc && mobileToc.hasAttribute('open')) {
          mobileToc.removeAttribute('open');
        }
      }
    });
  });

  // IntersectionObserver for active section highlighting on desktop
  const desktopTocItems = document.querySelectorAll('.blog-toc-list .toc-item');
  if (!desktopTocItems.length || !('IntersectionObserver' in window)) return;

  const observerOptions = {
    root: null,
    rootMargin: '-100px 0px -65% 0px',
    threshold: 0
  };

  let activeHeadingId = null;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        activeHeadingId = entry.target.id;
      }
    });

    if (activeHeadingId) {
      desktopTocItems.forEach(item => {
        const itemTarget = item.getAttribute('data-target');
        if (itemTarget === activeHeadingId) {
          item.classList.add('active');
        } else {
          item.classList.remove('active');
        }
      });
    }
  }, observerOptions);

  proseHeadings.forEach(heading => observer.observe(heading));
}

/* --------------------------------------------------------------------------
   17. BLOG DETAIL STICKY STRATEGY CALL FORM HANDLER
   -------------------------------------------------------------------------- */
function initBlogStrategyCallForm() {
  const form = document.querySelector('#blogStrategyForm');
  if (!form) return;

  const nameInput = form.querySelector('#strat_name');
  const emailInput = form.querySelector('#strat_email');
  const phoneInput = form.querySelector('#strat_phone');
  const submitBtn = form.querySelector('#stratSubmitBtn');
  const statusBox = form.querySelector('#stratFormStatus');

  const nameError = form.querySelector('#stratNameError');
  const emailError = form.querySelector('#stratEmailError');
  const phoneError = form.querySelector('#stratPhoneError');

  const clearErrors = () => {
    [nameInput, emailInput, phoneInput].forEach(inp => {
      if (inp) inp.classList.remove('input-error');
    });
    [nameError, emailError, phoneError].forEach(err => {
      if (err) {
        err.textContent = '';
        err.style.display = 'none';
      }
    });
    if (statusBox) {
      statusBox.style.display = 'none';
      statusBox.className = 'strategy-form-status';
      statusBox.textContent = '';
    }
  };

  [nameInput, emailInput, phoneInput].forEach(inp => {
    if (!inp) return;
    inp.addEventListener('input', () => {
      inp.classList.remove('input-error');
      const err = form.querySelector(`#${inp.id}Error`) || form.querySelector(`[id*="${inp.name.charAt(0).toUpperCase() + inp.name.slice(1)}Error"]`);
      if (err) {
        err.textContent = '';
        err.style.display = 'none';
      }
    });
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    clearErrors();

    let hasError = false;

    // Name Validation: Required, 2-150 chars
    const nameVal = nameInput ? nameInput.value.trim() : '';
    if (!nameVal || nameVal.length < 2) {
      if (nameInput) nameInput.classList.add('input-error');
      if (nameError) {
        nameError.textContent = 'Please enter your full name.';
        nameError.style.display = 'block';
      }
      hasError = true;
    }

    // Email Validation: Required, RFC email pattern
    const emailVal = emailInput ? emailInput.value.trim() : '';
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailVal || !emailRegex.test(emailVal)) {
      if (emailInput) emailInput.classList.add('input-error');
      if (emailError) {
        emailError.textContent = 'Please enter a valid email address.';
        emailError.style.display = 'block';
      }
      hasError = true;
    }

    // Phone Validation: Required, 10-digit Indian Mobile
    const phoneVal = phoneInput ? phoneInput.value.trim() : '';
    const cleanedPhone = phoneVal.replace(/[\s\-\(\)]+/g, '');
    const phoneRegex = /^(?:(?:\+|0{0,2})91)?[6789]\d{9}$/;
    if (!phoneVal || !phoneRegex.test(cleanedPhone)) {
      if (phoneInput) phoneInput.classList.add('input-error');
      if (phoneError) {
        phoneError.textContent = 'Please enter a valid 10-digit Indian mobile number.';
        phoneError.style.display = 'block';
      }
      hasError = true;
    }

    if (hasError) return;

    // Submit state
    const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.innerHTML = `<span>Booking Call...</span>`;
    }

    try {
      const formData = new FormData(form);
      const apiEndpoint = window.SITE_URL ? `${window.SITE_URL}/api/quick-enquiry.php` : (window.location.pathname.includes('/blog/') ? '../api/quick-enquiry.php' : '/api/quick-enquiry.php');

      const response = await fetch(apiEndpoint, {
        method: 'POST',
        headers: {
          'Accept': 'application/json'
        },
        body: formData
      });

      const result = await response.json();

      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnHtml;
      }

      if (result.success) {
        form.reset();
        if (statusBox) {
          statusBox.innerHTML = `<strong>Strategy Call Booked!</strong> Thank you ${nameVal}. Our senior growth team will contact you shortly.`;
          statusBox.className = 'strategy-form-status status-success';
          statusBox.style.display = 'block';
        }
      } else {
        if (statusBox) {
          const msg = result.message || 'Unable to record your request. Please try again.';
          statusBox.innerHTML = `<strong>Error:</strong> ${msg}`;
          statusBox.className = 'strategy-form-status status-error';
          statusBox.style.display = 'block';
        }
        if (result.errors) {
          if (result.errors.name && nameError) {
            nameError.textContent = result.errors.name;
            nameError.style.display = 'block';
          }
          if (result.errors.email && emailError) {
            emailError.textContent = result.errors.email;
            emailError.style.display = 'block';
          }
          if (result.errors.phone && phoneError) {
            phoneError.textContent = result.errors.phone;
            phoneError.style.display = 'block';
          }
        }
      }
    } catch (err) {
      console.error('Strategy call submission error:', err);
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnHtml;
      }
      if (statusBox) {
        statusBox.innerHTML = `<strong>Network Error:</strong> Unable to connect to the server. Please try again.`;
        statusBox.className = 'strategy-form-status status-error';
        statusBox.style.display = 'block';
      }
    }
  });
}

/* --------------------------------------------------------------------------
   18. TOP DYNAMIC ANNOUNCEMENT BAR HANDLER
   -------------------------------------------------------------------------- */
async function initAnnouncementBar() {
  const topBar = document.querySelector('#siteTopBar');
  const annWrap = document.querySelector('#dynamicAnnouncementWrap') || document.querySelector('#dynamicAnnouncementBar');
  if (!topBar && !annWrap) return;

  const getEndpoint = () => {
    if (window.SITE_URL) {
      return `${window.SITE_URL}/api/announcement.php`;
    }
    const path = decodeURIComponent(window.location.pathname || '');
    const ugcMatch = path.match(/^(\/[^\/]*ugc[^\/]*)\//i);
    if (ugcMatch) {
      return `${encodeURI(ugcMatch[1])}/api/announcement.php`;
    }
    return '/api/announcement.php';
  };

  try {
    const endpoint = getEndpoint();
    const res = await fetch(endpoint, {
      headers: { 'Accept': 'application/json' },
      cache: 'no-store'
    });
    if (!res.ok) return;
    const result = await res.json();
    if (!result || !result.success || !result.data) return;

    const data = result.data;
    if (annWrap) {
      if (data.is_enabled) {
        annWrap.style.display = 'flex';

        if (data.bg_color && topBar) {
          topBar.style.backgroundColor = data.bg_color;
        }

        const badgeEl = annWrap.querySelector('#announcementBadge') || annWrap.querySelector('.announcement-brand-badge');
        if (badgeEl && data.brand_text) {
          badgeEl.textContent = data.brand_text;
          if (data.text_color) {
            badgeEl.style.color = data.text_color;
            badgeEl.style.borderColor = data.text_color;
          }
        }

        const promoEl = annWrap.querySelector('#announcementPromoText') || annWrap.querySelector('.announcement-promo-text');
        if (promoEl && data.promotional_message) {
          promoEl.textContent = data.promotional_message;
          if (data.text_color) {
            promoEl.style.color = data.text_color;
          }
        }

        const linkEl = annWrap.querySelector('#dynamicAnnouncementLink') || annWrap.querySelector('.top-bar-announcement-link') || annWrap.querySelector('.announcement-content-link');
        if (linkEl && data.redirect_url) {
          let targetUrl = data.redirect_url;
          if (!targetUrl.startsWith('http') && !targetUrl.startsWith('/') && !targetUrl.startsWith('#')) {
            const pathParts = window.location.pathname.split('/').filter(Boolean);
            const isNested = pathParts.length > 1 && (pathParts[pathParts.length - 2] === 'services' || pathParts[pathParts.length - 2] === 'blogs' || pathParts[pathParts.length - 2] === 'blog');
            if (isNested && !targetUrl.startsWith('../')) {
              targetUrl = '../' + targetUrl;
            }
          }
          linkEl.setAttribute('href', targetUrl);
        }
      } else {
        annWrap.style.display = 'none';
      }
      updateHeaderHeight();
    }
  } catch (err) {
    console.warn('Could not load dynamic announcement bar:', err);
  } finally {
    updateHeaderHeight();
  }
}

/* --------------------------------------------------------------------------
   19. FLOATING CTA BUTTONS & INTERACTIVE CHATBOT DRAWER
   -------------------------------------------------------------------------- */
function initFloatingCtasAndChatbot() {
  const bubble = document.querySelector('#chatSpeechBubble');
  const bubbleClose = document.querySelector('#chatBubbleClose');
  const chatBtn = document.querySelector('#floatingChatbotBtn');
  const chatDrawer = document.querySelector('#siteChatDrawer');
  const chatClose = document.querySelector('#chatDrawerCloseBtn');
  const chatForm = document.querySelector('#chatMessageForm');
  const chatInput = document.querySelector('#chatMessageInput');
  const chatBody = document.querySelector('#chatDrawerBody');
  const chatChips = document.querySelectorAll('.chat-action-chip');

  // 1. "Chat with us!" 2.5s Delayed Bubble
  if (bubble) {
    const isDismissed = sessionStorage.getItem('myugc_chat_bubble_dismissed');
    if (!isDismissed) {
      setTimeout(() => {
        // Only show if drawer is not open
        if (!chatDrawer || chatDrawer.style.display === 'none' || !chatDrawer.classList.contains('active')) {
          bubble.style.display = 'inline-flex';
        }
      }, 2500);
    }

    if (bubbleClose) {
      bubbleClose.addEventListener('click', (e) => {
        e.stopPropagation();
        bubble.style.display = 'none';
        sessionStorage.setItem('myugc_chat_bubble_dismissed', '1');
      });
    }

    bubble.addEventListener('click', (e) => {
      if (e.target === bubbleClose) return;
      openChatDrawer();
    });
  }

  // 2. Open / Close Drawer Helpers
  const openChatDrawer = () => {
    if (!chatDrawer) return;
    chatDrawer.style.display = 'flex';
    chatDrawer.classList.add('active');
    chatDrawer.setAttribute('aria-hidden', 'false');
    if (chatBtn) {
      chatBtn.classList.add('is-active');
      chatBtn.setAttribute('aria-expanded', 'true');
    }
    if (bubble) {
      bubble.style.display = 'none';
      sessionStorage.setItem('myugc_chat_bubble_dismissed', '1');
    }
    if (chatInput) {
      setTimeout(() => chatInput.focus(), 100);
    }
  };

  const closeChatDrawer = () => {
    if (!chatDrawer) return;
    chatDrawer.style.display = 'none';
    chatDrawer.classList.remove('active');
    chatDrawer.setAttribute('aria-hidden', 'true');
    if (chatBtn) {
      chatBtn.classList.remove('is-active');
      chatBtn.setAttribute('aria-expanded', 'false');
    }
  };

  const toggleChatDrawer = () => {
    if (!chatDrawer) return;
    if (chatDrawer.style.display === 'flex' || chatDrawer.classList.contains('active')) {
      closeChatDrawer();
    } else {
      openChatDrawer();
    }
  };

  if (chatBtn) {
    chatBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      toggleChatDrawer();
    });
  }

  if (chatClose) {
    chatClose.addEventListener('click', (e) => {
      e.stopPropagation();
      closeChatDrawer();
    });
  }

  // Close on Escape key
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && chatDrawer && (chatDrawer.style.display === 'flex' || chatDrawer.classList.contains('active'))) {
      closeChatDrawer();
    }
  });

  // Close when clicking outside drawer
  document.addEventListener('click', (e) => {
    if (!chatDrawer || chatDrawer.style.display === 'none' || !chatDrawer.classList.contains('active')) return;
    if (!chatDrawer.contains(e.target) && !chatBtn.contains(e.target) && (!bubble || !bubble.contains(e.target))) {
      closeChatDrawer();
    }
  });

  // 3. Quick Action Chips
  chatChips.forEach(chip => {
    chip.addEventListener('click', (e) => {
      const action = chip.getAttribute('data-chat-action');
      if (action === 'inquiry') {
        closeChatDrawer();
        const target = document.querySelector('#project-inquiry') || document.querySelector('#contact');
        if (target) {
          target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
          const pathParts = window.location.pathname.split('/').filter(Boolean);
          const isNested = pathParts.length > 1 && (pathParts[pathParts.length - 2] === 'services' || pathParts[pathParts.length - 2] === 'blogs' || pathParts[pathParts.length - 2] === 'blog');
          const dest = window.SITE_URL ? `${window.SITE_URL}/contact` : (isNested ? '../contact' : 'contact');
          window.location.href = dest;
        }
      }
    });
  });

  // 4. Chat Message Form
  if (chatForm && chatInput && chatBody) {
    chatForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const text = chatInput.value.trim();
      if (!text) return;

      // Add user message
      const timeStr = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      const userMsg = document.createElement('div');
      userMsg.className = 'chat-msg chat-msg-user';
      userMsg.innerHTML = `<div class="chat-bubble">${escapeHtml(text)}</div><span class="chat-timestamp">${timeStr}</span>`;
      chatBody.appendChild(userMsg);
      chatInput.value = '';
      chatBody.scrollTop = chatBody.scrollHeight;

      // Assistant response with direct routing options
      setTimeout(() => {
        const botMsg = document.createElement('div');
        botMsg.className = 'chat-msg chat-msg-bot';
        botMsg.innerHTML = `
          <div class="chat-bubble">
            Thanks for reaching out! Our creative strategists are online. Would you like to chat directly on WhatsApp or fill out our project brief?
            <div class="chat-quick-actions" style="margin-top: 0.6rem;">
              <a href="https://wa.me/919876543210?text=${encodeURIComponent('Hi MY UGC VIDEO, ' + text)}" target="_blank" rel="noopener noreferrer" class="chat-action-chip" style="color:#25D366;border-color:rgba(37,211,102,0.4);">
                💬 Continue on WhatsApp (+91 98765 43210)
              </a>
              <button type="button" class="chat-action-chip" data-chat-action="inquiry">
                📋 Submit Project Brief / Get Quote
              </button>
            </div>
          </div>
          <span class="chat-timestamp">${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
        `;
        chatBody.appendChild(botMsg);
        chatBody.scrollTop = chatBody.scrollHeight;

        // Re-attach listener to dynamic button
        const newInquiryBtn = botMsg.querySelector('[data-chat-action="inquiry"]');
        if (newInquiryBtn) {
          newInquiryBtn.addEventListener('click', () => {
            closeChatDrawer();
            const target = document.querySelector('#project-inquiry') || document.querySelector('#contact');
            if (target) {
              target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
              const pathParts = window.location.pathname.split('/').filter(Boolean);
              const isNested = pathParts.length > 1 && (pathParts[pathParts.length - 2] === 'services' || pathParts[pathParts.length - 2] === 'blogs' || pathParts[pathParts.length - 2] === 'blog');
              const dest = window.SITE_URL ? `${window.SITE_URL}/contact` : (isNested ? '../contact' : 'contact');
              window.location.href = dest;
            }
          });
        }
      }, 500);
    });
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }
}

/* --------------------------------------------------------------------------
   CONTACT FORM POPUP — AUTOMATIC 5-SECOND INQUIRY DIALOG
   Fires 5 seconds after page load on eligible pages.
   Restarts timer on every page refresh (no sessionStorage/localStorage/cookies).
   In-memory per-page-load suppression only (does not re-open in same view once closed/submitted).
   -------------------------------------------------------------------------- */
let contactPopupHandledThisPage = false;

function isContactPopupEligiblePage() {
  try {
    const rawPath = window.location.pathname || '';
    const pathname = decodeURIComponent(rawPath).toLowerCase().replace(/\/+/g, '/');
    const search = (window.location.search || '').toLowerCase();

    // 1. Exclude Admin, Login, Config, API, includes, etc.
    if (pathname.includes('/admin') ||
        pathname.includes('/api') ||
        pathname.includes('/config') ||
        pathname.includes('/includes') ||
        pathname.includes('/login') ||
        pathname.includes('/logout')) {
      return false;
    }

    // 2. Exclude Contact page
    if (pathname.endsWith('/contact') ||
        pathname.endsWith('/contact/') ||
        pathname.endsWith('/contact.html') ||
        pathname.includes('/contact.')) {
      return false;
    }

    // 3. Exclude Dedicated Service pages (Note: /services, /services.html is ELIGIBLE)
    const isServicesOverview = pathname.endsWith('/services') ||
                               pathname.endsWith('/services/') ||
                               pathname.endsWith('/services.html');
    if (pathname.includes('/services/')) {
      const parts = pathname.split('/services/');
      const sub = parts[1] ? parts[1].trim() : '';
      if (sub && sub !== 'index.html' && sub !== 'services.html') {
        return false;
      }
    }

    // 4. Exclude Individual Blog article pages (Note: /blogs, /blogs.php, /blogs.html is ELIGIBLE)
    if (pathname.includes('/blog/') || pathname.endsWith('/blog')) {
      return false;
    }
    if (pathname.endsWith('/blog.php') && search.includes('slug=')) {
      return false;
    }

    // DOM-based safeguard checks
    if (document.body && (document.body.classList.contains('blog-detail-page') || document.querySelector('.article-title'))) {
      return false;
    }
    if (!isServicesOverview && document.querySelector('.service-hero-section')) {
      return false;
    }
    if (document.querySelector('.contact-hero-editorial') || document.querySelector('.contact-hero-section')) {
      return false;
    }

    return true;
  } catch (e) {
    return false;
  }
}

function initContactPopup() {
  // Check eligibility first
  if (!isContactPopupEligiblePage()) return;
  if (contactPopupHandledThisPage) return;

  let popupTimer = null;

  // Schedule popup for exactly 5 seconds after load
  popupTimer = setTimeout(() => {
    if (!contactPopupHandledThisPage) {
      openContactPopup();
    }
  }, 5000);

  // If user navigates away before 5 seconds, cancel timer
  window.addEventListener('beforeunload', () => {
    if (popupTimer) clearTimeout(popupTimer);
  });
  window.addEventListener('pagehide', () => {
    if (popupTimer) clearTimeout(popupTimer);
  });
}

function openContactPopup(force = false) {
  // Prevent duplicate instances
  if (document.querySelector('#siteContactPopup')) {
    const existing = document.querySelector('#siteContactPopup');
    existing.classList.add('active');
    existing.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    return;
  }

  // Double-check eligibility and in-memory flag (restarts on page refresh)
  if (!force && (!isContactPopupEligiblePage() || contactPopupHandledThisPage)) return;
  contactPopupHandledThisPage = true;

  // Inject markup
  const overlay = document.createElement('div');
  overlay.id = 'siteContactPopup';
  overlay.className = 'contact-popup-overlay';
  overlay.setAttribute('role', 'dialog');
  overlay.setAttribute('aria-modal', 'true');
  overlay.setAttribute('aria-labelledby', 'popupTitle');
  overlay.setAttribute('aria-hidden', 'false');

  overlay.innerHTML = `
    <div class="contact-popup-container">
      <div class="contact-popup-card" role="document">
        <button type="button" class="contact-popup-close" id="popupCloseBtn" aria-label="Close inquiry dialog">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>

        <!-- FORM VIEW -->
        <div id="popupFormView" class="popup-view-form">
          <div class="contact-popup-header">
            <div class="contact-popup-badge font-display">
              <span class="badge-dot" aria-hidden="true"></span>
              <span>QUICK INQUIRY</span>
            </div>
            <h3 id="popupTitle" class="contact-popup-title font-display">
              Let's Create High-Converting <span class="highlight-mint">UGC Videos</span>
            </h3>
            <p class="contact-popup-desc">
              Tell us about your brand. Our creative directors will review your inquiry and reply within 24 business hours.
            </p>
          </div>

          <div id="popupStatusAlert" class="form-status-alert" style="display: none;" role="alert"></div>

          <form id="popupInquiryForm" class="contact-popup-form" novalidate>
            <input type="text" name="website_hp" style="display:none !important;" tabindex="-1" autocomplete="off">
            
            <div class="popup-form-grid">
              <!-- Full Name -->
              <div class="form-group">
                <label for="popupFullName" class="form-label">Full Name <span class="req">*</span></label>
                <div class="popup-input-wrapper">
                  <input type="text" id="popupFullName" name="name" class="form-control" placeholder="e.g. John Doe" required autocomplete="name">
                  <span class="popup-input-icon" aria-hidden="true">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                      <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                  </span>
                </div>
                <span class="form-error-msg"></span>
              </div>

              <!-- Email Address -->
              <div class="form-group">
                <label for="popupWorkEmail" class="form-label">Email Address <span class="req">*</span></label>
                <div class="popup-input-wrapper">
                  <input type="email" id="popupWorkEmail" name="email" class="form-control" placeholder="e.g. john@example.com" required autocomplete="email">
                  <span class="popup-input-icon" aria-hidden="true">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                      <polyline points="22,6 12,13 2,6"></polyline>
                    </svg>
                  </span>
                </div>
                <span class="form-error-msg"></span>
              </div>

              <!-- Phone Number -->
              <div class="form-group full-width">
                <label for="popupPhoneNumber" class="form-label">Phone Number <span class="req">*</span></label>
                <div class="popup-input-wrapper">
                  <input type="tel" id="popupPhoneNumber" name="phone" class="form-control" placeholder="e.g. 00000 00000" required autocomplete="tel">
                  <span class="popup-input-icon" aria-hidden="true">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                    </svg>
                  </span>
                </div>
                <span class="form-error-msg"></span>
              </div>

              <!-- Message -->
              <div class="form-group full-width">
                <div class="popup-field-header">
                  <label for="popupMessage" class="form-label">Message <span class="optional-tag">(Optional)</span></label>
                  <span class="popup-char-counter" id="popupCharCounter"><span id="popupCharCount">0</span>/500</span>
                </div>
                <div class="popup-input-wrapper">
                  <textarea id="popupMessage" name="message" class="form-control" rows="3" maxlength="500" placeholder="Tell us about your product, goals, or timeline..."></textarea>
                </div>
                <span class="form-error-msg"></span>
              </div>
            </div>

            <div class="popup-form-footer">
              <button type="submit" class="btn btn-primary popup-submit-btn" id="popupSubmitBtn">
                <span class="btn-text">Send Inquiry</span>
                <svg class="btn-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                  <line x1="5" y1="12" x2="19" y2="12"></line>
                  <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
                <span class="btn-spinner" aria-hidden="true" style="display:none;"></span>
              </button>
              <p class="form-privacy-note">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-1px; margin-right:4px;">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                  <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                Your information is protected. We respect your privacy. No spam, ever.
              </p>
            </div>
          </form>
        </div>

        <!-- SUCCESS VIEW (Connected to actual PHP submission) -->
        <div id="popupSuccessView" class="popup-view-success" style="display: none;">
          <div class="success-icon-badge" aria-hidden="true">
            <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#36D9B0" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="20 6 9 17 4 12"></polyline>
            </svg>
          </div>
          <h3 class="success-title font-display">Inquiry Received!</h3>
          <p class="success-desc">
            Thank you for reaching out. Our creative directors will review your brief and get back to you within 24 business hours.
          </p>
          <div class="success-actions">
            <button type="button" class="btn btn-outline popup-new-inquiry-btn" id="popupNewInquiryBtn">
              <span>New Inquiry</span>
            </button>
            <button type="button" class="btn btn-primary popup-done-btn" id="popupDoneBtn">
              <span>Done</span>
            </button>
          </div>
        </div>

      </div>
    </div>
  `;

  document.body.appendChild(overlay);

  // Trigger smooth entrance animation in next frame
  requestAnimationFrame(() => {
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
    const firstInput = overlay.querySelector('#popupFullName');
    if (firstInput) firstInput.focus();
  });

  // Setup close events
  const closeBtn = overlay.querySelector('#popupCloseBtn');
  if (closeBtn) {
    closeBtn.addEventListener('click', closeContactPopup);
  }

  overlay.addEventListener('click', (e) => {
    if (e.target === overlay) {
      closeContactPopup();
    }
  });

  document.addEventListener('keydown', handlePopupKeydown);

  // Setup live character counter
  const msgInput = overlay.querySelector('#popupMessage');
  const charCounter = overlay.querySelector('#popupCharCount');
  if (msgInput && charCounter) {
    msgInput.addEventListener('input', () => {
      charCounter.textContent = msgInput.value.length;
    });
  }

  // Setup success action buttons
  const newInquiryBtn = overlay.querySelector('#popupNewInquiryBtn');
  const doneBtn = overlay.querySelector('#popupDoneBtn');
  const formView = overlay.querySelector('#popupFormView');
  const successView = overlay.querySelector('#popupSuccessView');

  if (newInquiryBtn) {
    newInquiryBtn.addEventListener('click', () => {
      if (successView) successView.style.display = 'none';
      if (formView) formView.style.display = 'block';
      const form = overlay.querySelector('#popupInquiryForm');
      if (form) form.reset();
      if (charCounter) charCounter.textContent = '0';
      const firstInput = overlay.querySelector('#popupFullName');
      if (firstInput) firstInput.focus();
    });
  }

  if (doneBtn) {
    doneBtn.addEventListener('click', () => {
      closeContactPopup();
    });
  }

  // Bind Form Submission
  bindPopupFormSubmission(overlay);
}

function handlePopupKeydown(e) {
  if (e.key === 'Escape') {
    closeContactPopup();
  }
}

function closeContactPopup() {
  const overlay = document.querySelector('#siteContactPopup');
  if (!overlay) return;

  contactPopupHandledThisPage = true;

  overlay.classList.remove('active');
  overlay.setAttribute('aria-hidden', 'true');
  document.body.style.overflow = '';
  document.removeEventListener('keydown', handlePopupKeydown);

  setTimeout(() => {
    if (overlay && overlay.parentNode) {
      overlay.parentNode.removeChild(overlay);
    }
  }, 350);
}

function bindPopupFormSubmission(overlay) {
  const form = overlay.querySelector('#popupInquiryForm');
  if (!form) return;

  const statusAlert = overlay.querySelector('#popupStatusAlert');
  const formView = overlay.querySelector('#popupFormView');
  const successView = overlay.querySelector('#popupSuccessView');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    let isValid = true;

    // Reset previous errors
    form.querySelectorAll('.form-control').forEach(ctrl => ctrl.classList.remove('error'));
    form.querySelectorAll('.form-error-msg').forEach(msg => {
      msg.classList.remove('visible');
      msg.textContent = '';
    });
    if (statusAlert) {
      statusAlert.className = 'form-status-alert';
      statusAlert.style.display = 'none';
      statusAlert.textContent = '';
    }

    function showPopupError(input, message) {
      if (!input) return;
      input.classList.add('error');
      const group = input.closest('.form-group');
      if (group) {
        const errorEl = group.querySelector('.form-error-msg');
        if (errorEl) {
          errorEl.textContent = message;
          errorEl.classList.add('visible');
        }
      }
    }

    // 1. Validate Full Name (Required)
    const nameInput = form.querySelector('#popupFullName');
    if (nameInput && nameInput.value.trim().length < 2) {
      showPopupError(nameInput, 'Please provide your full name.');
      isValid = false;
    }

    // 2. Validate Email Address (Required)
    const emailInput = form.querySelector('#popupWorkEmail');
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (emailInput && !emailRegex.test(emailInput.value.trim())) {
      showPopupError(emailInput, 'Please provide a valid email address.');
      isValid = false;
    }

    // 3. Validate Phone Number (Required)
    const phoneInput = form.querySelector('#popupPhoneNumber');
    const phoneVal = phoneInput ? phoneInput.value.trim() : '';
    const phoneRegex = /^[+]?[\d\s\-().]{7,25}$/;
    if (!phoneInput || phoneVal.length < 7 || !phoneRegex.test(phoneVal)) {
      showPopupError(phoneInput, 'Please provide a valid phone number (at least 7 digits).');
      isValid = false;
    }

    // 4. Message is Optional (max 500 chars)
    const msgInput = form.querySelector('#popupMessage');
    if (msgInput && msgInput.value.trim().length > 500) {
      showPopupError(msgInput, 'Message is too long (maximum 500 characters).');
      isValid = false;
    }

    if (!isValid) return;

    // Submit via centralized endpoint
    const submitBtn = form.querySelector('#popupSubmitBtn');
    const btnText = submitBtn ? submitBtn.querySelector('.btn-text') : null;
    const btnArrow = submitBtn ? submitBtn.querySelector('.btn-arrow') : null;
    const btnSpinner = submitBtn ? submitBtn.querySelector('.btn-spinner') : null;

    if (submitBtn) submitBtn.disabled = true;
    if (btnText) btnText.textContent = 'Sending Inquiry...';
    if (btnArrow) btnArrow.style.display = 'none';
    if (btnSpinner) btnSpinner.style.display = 'inline-block';

    try {
      const csrfToken = await getLeadCsrfToken();
      const formData = new FormData(form);
      formData.set('source', 'Contact Popup Form');
      formData.set('csrf_token', csrfToken);

      if (nameInput) formData.set('name', nameInput.value.trim());
      if (emailInput) formData.set('email', emailInput.value.trim());
      if (phoneInput) formData.set('phone', phoneInput.value.trim());
      formData.set('message', msgInput && msgInput.value.trim() ? msgInput.value.trim() : '');

      const response = await fetch(getLeadApiEndpoint(), {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken
        },
        credentials: 'same-origin',
        body: formData
      });

      const result = await response.json();

      if (submitBtn) submitBtn.disabled = false;
      if (btnText) btnText.textContent = 'Send Inquiry';
      if (btnArrow) btnArrow.style.display = '';
      if (btnSpinner) btnSpinner.style.display = 'none';

      if (result.success) {
        form.reset();
        const charCounter = overlay.querySelector('#popupCharCount');
        if (charCounter) charCounter.textContent = '0';

        // Switch to attractive Success Screen
        if (formView) formView.style.display = 'none';
        if (successView) {
          successView.style.display = 'block';
        }
      } else {
        if (statusAlert) {
          statusAlert.className = 'form-status-alert error';
          statusAlert.style.display = 'block';
          const msg = result.message || 'We could not submit your inquiry at this moment. Please check your inputs and try again.';
          statusAlert.innerHTML = `<strong>Submission Error:</strong> ${msg}`;
          statusAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
      }
    } catch (err) {
      if (submitBtn) submitBtn.disabled = false;
      if (btnText) btnText.textContent = 'Send Inquiry';
      if (btnArrow) btnArrow.style.display = '';
      if (btnSpinner) btnSpinner.style.display = 'none';

      if (statusAlert) {
        statusAlert.className = 'form-status-alert error';
        statusAlert.style.display = 'block';
        statusAlert.innerHTML = '<strong>Network error:</strong> Unable to reach server. Please try again.';
        statusAlert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
    }
  });
}
