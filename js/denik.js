// Okamžitý filtr deníku bez načítání stránky. Bez JavaScriptu funguje formulář klasicky.
(function () {
  var form = document.querySelector('.filters');
  var cards = Array.prototype.slice.call(document.querySelectorAll('#cards .card'));
  if (!form || !cards.length) return;
  document.documentElement.classList.add('js');
  var count = document.getElementById('count');
  var empty = document.getElementById('empty');
  var reset = document.getElementById('reset');
  var timer;
  // Názvy polí filtru: deník typ/misto, Výbava a Proviant mají vlastní (data-p1, data-p2)
  var P1 = form.dataset.p1 || 'typ', P2 = form.dataset.p2 || 'misto';

  function norm(s) { return (s || '').toLocaleLowerCase('cs'); }

  function apply() {
    var data = new FormData(form);
    var typ = data.get(P1) || '', misto = data.get(P2) || '', rok = data.get('rok') || '';
    var q = norm((data.get('q') || '').trim());
    var shown = 0;
    cards.forEach(function (c) {
      var ok = (!typ || c.dataset.type.split(' ').indexOf(typ) !== -1)
        && (!misto || c.dataset.places.split('|').indexOf(misto) !== -1)
        && (!rok || c.dataset.year === rok)
        && (!q || c.dataset.search.indexOf(q) !== -1);
      c.hidden = !ok;
      if (ok) shown++;
    });
    count.textContent = shown;
    document.querySelectorAll('.place-note').forEach(function (n) { n.hidden = n.dataset.place !== misto; });
    empty.hidden = shown > 0;
    var params = new URLSearchParams();
    if (typ) params.set(P1, typ);
    if (misto) params.set(P2, misto);
    if (rok) params.set('rok', rok);
    if (q) params.set('q', data.get('q').trim());
    var qs = params.toString();
    reset.hidden = !qs;
    history.replaceState(null, '', location.pathname + (qs ? '?' + qs : ''));
  }

  // Filtr z adresy (např. odkaz z úvodu na místo) i tam, kde ho nezpracuje server
  var init = new URLSearchParams(location.search);
  if (init.toString()) {
    var t = form.querySelector('input[name="' + P1 + '"][value="' + (init.get(P1) || '') + '"]');
    if (t) t.checked = true;
    [P2, 'rok', 'q'].forEach(function (k) {
      var el = form.querySelector('[name="' + k + '"]');
      if (el && init.get(k)) el.value = init.get(k);
    });
    apply();
  }

  form.addEventListener('change', apply);
  form.addEventListener('input', function (e) {
    if (e.target.name !== 'q') return;
    clearTimeout(timer);
    timer = setTimeout(apply, 200);
  });
  form.addEventListener('submit', function (e) { e.preventDefault(); apply(); });
  reset.addEventListener('click', function (e) {
    e.preventDefault();
    form.reset();
    form.querySelector('input[name="' + P1 + '"][value=""]').checked = true;
    form.querySelector('select[name="' + P2 + '"]').value = '';
    form.querySelector('select[name="rok"]').value = '';
    form.querySelector('input[name="q"]').value = '';
    apply();
  });
})();
