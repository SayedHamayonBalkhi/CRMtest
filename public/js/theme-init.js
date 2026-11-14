// Runs before first paint so a saved dark theme does not flash light.
try {
  document.documentElement.dataset.theme = localStorage.getItem('eb-theme') === 'dark' ? 'dark' : 'light';
} catch (e) {
  document.documentElement.dataset.theme = 'light';
}
