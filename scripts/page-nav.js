function pinPageNav() {
  const header = document.querySelector('body > header');
  const headerBottom = header ? Math.round(header.getBoundingClientRect().bottom) : 70;
  const desktop = window.matchMedia('(min-width: 1100px)').matches;
  document.querySelectorAll('.page-nav').forEach((nav) => {
    if (!desktop) {
      let spacer = nav.previousElementSibling;
      if (!spacer || !spacer.classList.contains('page-nav-spacer')) {
        spacer = document.createElement('div');
        spacer.className = 'page-nav-spacer';
        nav.parentElement.insertBefore(spacer, nav);
      }
      nav.style.position = 'fixed';
      nav.style.top = headerBottom + 'px';
      nav.style.left = '10px';
      nav.style.right = '10px';
      nav.style.width = 'auto';
      nav.style.zIndex = '9';
      nav.style.margin = '0';
      spacer.style.height = (nav.offsetHeight + 14) + 'px';
      return;
    }
    const spacer = nav.previousElementSibling;
    if (spacer && spacer.classList.contains('page-nav-spacer')) {
      spacer.remove();
    }
    nav.style.position = '';
    nav.style.left = '';
    nav.style.right = '';
    nav.style.width = '';
    nav.style.zIndex = '';
    nav.style.margin = '';
    if (window.scrollY < 2) {
      nav.style.top = '';
      const naturalTop = Math.round(nav.getBoundingClientRect().top);
      nav.dataset.pinTop = String(naturalTop);
      nav.style.top = naturalTop + 'px';
      document.documentElement.style.setProperty('--page-nav-top', naturalTop + 'px');
    } else if (nav.dataset.pinTop) {
      nav.style.top = nav.dataset.pinTop + 'px';
    } else {
      nav.style.top = (headerBottom + 12) + 'px';
    }
  });
}

function initPageNav() {
  document.querySelectorAll('.page-nav[data-nav="auto"]').forEach((nav) => {
    const root = nav.closest('.contain') || document;
    const sections = Array.from(root.querySelectorAll('.account-section, .stats-section')).filter((section) => {
      return section.querySelector('h2');
    });
    if (sections.length < 3) {
      const contain = nav.closest('.contain');
      nav.remove();
      if (contain) {
        contain.classList.remove('has-page-nav');
      }
      return;
    }

    nav.innerHTML = '';
    const title = document.createElement('h3');
    title.className = 'page-nav-title';
    title.textContent = window.pageNavTitle || ((window.lang === 'zh_cn' || window.lang === 'zh_tw') ? '目录' : 'Contents');
    nav.appendChild(title);
    const links = [];
    let locked = false;
    const jump = document.createElement('select');
    jump.className = 'page-nav-jump';
    jump.setAttribute('aria-label', title.textContent);
    nav.appendChild(jump);

    const setActive = (id) => {
      links.forEach((link) => {
        link.classList.toggle('is-active', link.getAttribute('href') === '#' + id);
      });
      if (jump.value !== id) {
        jump.value = id || '';
      }
    };

    const goTo = (section) => {
      locked = true;
      setActive(section.id);
      section.classList.remove('section-flash');
      void section.offsetWidth;
      section.classList.add('section-flash');
      section.scrollIntoView({ behavior: 'smooth', block: 'start' });
      history.replaceState(null, '', '#' + section.id);
      window.setTimeout(() => {
        locked = false;
        section.classList.remove('section-flash');
      }, 800);
    };

    sections.forEach((section, index) => {
      const heading = section.querySelector('h2');
      const label = heading ? heading.textContent.replace(/\s+/g, ' ').trim() : '';
      if (!label) {
        return;
      }
      if (!section.id) {
        section.id = 'section-' + index;
      }
      const link = document.createElement('a');
      link.href = '#' + section.id;
      link.textContent = label;
      link.addEventListener('click', (event) => {
        event.preventDefault();
        goTo(section);
      });
      nav.appendChild(link);
      links.push(link);

      const option = document.createElement('option');
      option.value = section.id;
      option.textContent = label;
      jump.appendChild(option);
    });

    jump.addEventListener('change', () => {
      const target = document.getElementById(jump.value);
      if (target) {
        goTo(target);
      }
    });

    const observer = new IntersectionObserver((entries) => {
      if (locked) {
        return;
      }
      const visible = entries
        .filter((entry) => entry.isIntersecting)
        .sort((a, b) => b.intersectionRatio - a.intersectionRatio)[0];
      if (!visible) {
        return;
      }
      setActive(visible.target.id);
    }, { rootMargin: '-20% 0px -65% 0px', threshold: [0.15, 0.35, 0.6] });

    sections.forEach((section) => observer.observe(section));
    if (sections[0]) {
      setActive(sections[0].id);
    }

    if (window.matchMedia('(min-width: 1100px)').matches
        && nav.parentElement
        && !nav.parentElement.classList.contains('page-nav-rail')) {
      const rail = document.createElement('div');
      rail.className = 'page-nav-rail';
      nav.parentElement.insertBefore(rail, nav);
      rail.appendChild(nav);
    }
  });

  pinPageNav();
  window.addEventListener('resize', pinPageNav);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPageNav);
} else {
  initPageNav();
}
