/* Administrace: hledání v seznamu, potvrzení smazání, zmenšení fotek před odesláním. */
(function () {
  'use strict';

  var q = document.getElementById('hledat');
  if (q) {
    var items = document.querySelectorAll('#seznam li');
    q.addEventListener('input', function () {
      var v = q.value.trim().toLowerCase();
      items.forEach(function (li) { li.hidden = v !== '' && li.dataset.search.indexOf(v) === -1; });
    });
  }

  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      if (!window.confirm(f.dataset.confirm)) ev.preventDefault();
    });
  });

  // Fotky z mobilu mívají 12+ Mpx; zmenšíme je v prohlížeči, ať projdou limitem hostingu.
  var MAX = 2000;
  var input = document.querySelector('input[type=file][data-max]');
  var form = document.getElementById('zaznam');
  var stav = document.getElementById('stav');
  if (!input || !form || !window.DataTransfer || !HTMLCanvasElement.prototype.toBlob) return;

  function shrink(file) {
    return new Promise(function (resolve) {
      if (!/^image\/(jpeg|png|webp)$/.test(file.type)) return resolve(file);
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () {
        URL.revokeObjectURL(url);
        var k = Math.min(1, MAX / Math.max(img.naturalWidth, img.naturalHeight));
        if (k === 1 && file.size < 3e6) return resolve(file);
        var c = document.createElement('canvas');
        c.width = Math.round(img.naturalWidth * k);
        c.height = Math.round(img.naturalHeight * k);
        var ctx = c.getContext('2d');
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, c.width, c.height);
        ctx.drawImage(img, 0, 0, c.width, c.height);
        c.toBlob(function (b) {
          resolve(b ? new File([b], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file);
        }, 'image/jpeg', 0.88);
      };
      img.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
      img.src = url;
    });
  }

  input.addEventListener('change', function () {
    var max = parseInt(input.dataset.max, 10);
    var files = Array.prototype.slice.call(input.files);
    if (files.length > max) {
      window.alert('Můžete přidat nejvýš ' + max + '. Použije se prvních ' + max + '.');
      files = files.slice(0, max);
    }
    if (!files.length) return;
    stav.textContent = 'Připravuji fotky…';
    form.querySelector('button[type=submit]').disabled = true;
    Promise.all(files.map(shrink)).then(function (out) {
      var dt = new DataTransfer();
      out.forEach(function (f) { dt.items.add(f); });
      input.files = dt.files;
      stav.textContent = 'Fotky připravené (' + out.length + '). Uložte záznam.';
      form.querySelector('button[type=submit]').disabled = false;
    });
  });

  form.addEventListener('submit', function () {
    if (input.files.length) stav.textContent = 'Nahrávám fotky, chvíli strpení…';
  });
})();
