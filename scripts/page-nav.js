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
    const links = [];
    let locked = false;

    const setActive = (activeLink) => {
      links.forEach((link) => {
        link.classList.toggle('is-active', link === activeLink);
      });
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
        locked = true;
        setActive(link);
        section.classList.remove('section-flash');
        void section.offsetWidth;
        section.classList.add('section-flash');
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        history.replaceState(null, '', '#' + section.id);
        window.setTimeout(() => {
          locked = false;
          section.classList.remove('section-flash');
        }, 800);
      });
      nav.appendChild(link);
      links.push(link);
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
      const match = links.find((link) => link.getAttribute('href') === '#' + visible.target.id);
      if (match) {
        setActive(match);
      }
    }, { rootMargin: '-20% 0px -65% 0px', threshold: [0.15, 0.35, 0.6] });

    sections.forEach((section) => observer.observe(section));
    if (links[0]) {
      setActive(links[0]);
    }
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPageNav);
} else {
  initPageNav();
}
