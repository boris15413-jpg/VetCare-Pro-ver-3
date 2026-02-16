// VetCare Pro Docs - Premium Interactions v2.0
document.addEventListener('DOMContentLoaded', () => {
  // Navbar scroll effect
  const navbar = document.querySelector('.vc-navbar');
  if (navbar) {
    let lastScroll = 0;
    window.addEventListener('scroll', () => {
      const curr = window.scrollY;
      navbar.classList.toggle('scrolled', curr > 20);
      lastScroll = curr;
    }, { passive: true });
  }

  // Mobile hamburger
  const hamburger = document.querySelector('.vc-hamburger');
  const nav = document.querySelector('.vc-nav');
  if (hamburger && nav) {
    hamburger.addEventListener('click', () => {
      nav.classList.toggle('open');
      const spans = hamburger.querySelectorAll('span');
      if (nav.classList.contains('open')) {
        spans[0].style.transform = 'rotate(45deg) translate(5px,5px)';
        spans[1].style.opacity = '0';
        spans[2].style.transform = 'rotate(-45deg) translate(5px,-5px)';
      } else {
        spans[0].style.transform = '';
        spans[1].style.opacity = '';
        spans[2].style.transform = '';
      }
    });
    // Close on outside click
    document.addEventListener('click', (e) => {
      if (!nav.contains(e.target) && !hamburger.contains(e.target)) {
        nav.classList.remove('open');
        hamburger.querySelectorAll('span').forEach(s => { s.style.transform = ''; s.style.opacity = ''; });
      }
    });
    // Close on nav link click
    nav.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        nav.classList.remove('open');
        hamburger.querySelectorAll('span').forEach(s => { s.style.transform = ''; s.style.opacity = ''; });
      });
    });
  }

  // Accordions
  document.querySelectorAll('.vc-accordion-header').forEach(header => {
    header.addEventListener('click', () => {
      const acc = header.parentElement;
      const isOpen = acc.classList.contains('open');
      // Close all in same group
      const group = acc.closest('.vc-accordion-group');
      if (group) {
        group.querySelectorAll('.vc-accordion').forEach(a => a.classList.remove('open'));
      }
      if (!isOpen) acc.classList.add('open');
    });
  });

  // Tabs
  document.querySelectorAll('.vc-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      const group = tab.closest('.vc-tabs-wrapper');
      if (!group) return;
      group.querySelectorAll('.vc-tab').forEach(t => t.classList.remove('active'));
      group.querySelectorAll('.vc-tab-panel').forEach(p => p.classList.remove('active'));
      tab.classList.add('active');
      const target = group.querySelector(tab.dataset.target);
      if (target) target.classList.add('active');
    });
  });

  // Scroll reveal (single elements)
  const reveals = document.querySelectorAll('[data-reveal]');
  if (reveals.length) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          e.target.classList.add('revealed');
          io.unobserve(e.target);
        }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
    reveals.forEach(el => io.observe(el));
  }

  // Stagger reveal (container with children)
  const staggers = document.querySelectorAll('[data-reveal-stagger]');
  if (staggers.length) {
    const sio = new IntersectionObserver((entries) => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          e.target.classList.add('revealed');
          sio.unobserve(e.target);
        }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });
    staggers.forEach(el => sio.observe(el));
  }

  // TOC active tracking
  const tocLinks = document.querySelectorAll('.vc-toc a');
  if (tocLinks.length) {
    const sections = [];
    tocLinks.forEach(a => {
      const id = a.getAttribute('href')?.replace('#', '');
      const el = id ? document.getElementById(id) : null;
      if (el) sections.push({ link: a, el });
    });
    if (sections.length) {
      const updateToc = () => {
        let current = sections[0];
        sections.forEach(s => {
          if (s.el.getBoundingClientRect().top <= 120) current = s;
        });
        tocLinks.forEach(a => a.classList.remove('active'));
        if (current) current.link.classList.add('active');
      };
      window.addEventListener('scroll', updateToc, { passive: true });
      updateToc();
    }
  }

  // Copy code blocks
  document.querySelectorAll('pre').forEach(pre => {
    const btn = document.createElement('button');
    btn.textContent = 'Copy';
    btn.style.cssText = 'position:absolute;top:10px;right:10px;padding:5px 14px;border-radius:8px;border:1px solid rgba(255,255,255,0.15);background:rgba(255,255,255,0.06);color:#94a3b8;font-size:0.72rem;cursor:pointer;font-family:inherit;transition:all 0.2s;backdrop-filter:blur(8px);';
    btn.addEventListener('mouseenter', () => { btn.style.background = 'rgba(255,255,255,0.12)'; btn.style.color = '#e2e8f0'; });
    btn.addEventListener('mouseleave', () => { btn.style.background = 'rgba(255,255,255,0.06)'; btn.style.color = '#94a3b8'; });
    btn.addEventListener('click', () => {
      const code = pre.querySelector('code') || pre;
      const text = code.textContent.replace(/^Copy|^Copied!/, '').trim();
      navigator.clipboard.writeText(text).then(() => {
        btn.textContent = 'Copied!';
        btn.style.color = '#34d399';
        setTimeout(() => { btn.textContent = 'Copy'; btn.style.color = '#94a3b8'; }, 2000);
      });
    });
    pre.style.position = 'relative';
    pre.appendChild(btn);
  });

  // Smooth scroll for anchor links
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
      const id = a.getAttribute('href').slice(1);
      const el = document.getElementById(id);
      if (el) {
        e.preventDefault();
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        history.replaceState(null, '', '#' + id);
      }
    });
  });
});
