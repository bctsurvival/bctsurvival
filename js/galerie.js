// Zvětšení fotky po kliknutí (prvek <dialog>). Bez JavaScriptu se fotka otevře samostatně.
(function () {
  var dlg = document.getElementById('lightbox');
  if (!dlg || typeof dlg.showModal !== 'function') return;
  var img = dlg.querySelector('img');
  var cap = dlg.querySelector('.lb-cap');
  document.querySelectorAll('.zoom').forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      img.src = a.href;
      img.alt = a.querySelector('img').alt;
      cap.textContent = a.dataset.caption || '';
      cap.hidden = !a.dataset.caption;
      dlg.showModal();
    });
  });
  dlg.addEventListener('click', function (e) { if (e.target === dlg || e.target === img) dlg.close(); });
})();
