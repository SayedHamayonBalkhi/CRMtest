// Sidebar (collapse on desktop, drawer on mobile) and the light/dark toggle. Imported by every page module.
const shell = document.querySelector('.shell');
const sidebar = document.getElementById('sidebar');
const scrim = document.getElementById('scrim');
const desktop = window.matchMedia('(min-width: 768px)');

function setDrawer(open) {
  sidebar.classList.toggle('is-open', open);
  scrim.hidden = !open;
}

document.getElementById('menu-btn').addEventListener('click', () => {
  if (desktop.matches) shell.classList.toggle('is-collapsed');
  else setDrawer(!sidebar.classList.contains('is-open'));
});
scrim.addEventListener('click', () => setDrawer(false));
document.addEventListener('keydown', (e) => e.key === 'Escape' && setDrawer(false));
desktop.addEventListener('change', () => setDrawer(false));

const choices = document.querySelectorAll('[data-theme-choice]');
function showTheme() {
  const current = document.documentElement.dataset.theme;
  choices.forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.themeChoice === current)));
}
choices.forEach((b) =>
  b.addEventListener('click', () => {
    document.documentElement.dataset.theme = b.dataset.themeChoice;
    try {
      localStorage.setItem('eb-theme', b.dataset.themeChoice);
    } catch {
      // Storage blocked: the theme still applies for this visit.
    }
    showTheme();
  }),
);
showTheme();
