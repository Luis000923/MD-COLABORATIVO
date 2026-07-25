(function () {
  if (typeof mermaid === 'undefined') return;

  var oscuro = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  mermaid.initialize({ startOnLoad: false, theme: oscuro ? 'dark' : 'default' });

  var blocks = document.querySelectorAll('code.language-mermaid');
  if (!blocks.length) return;

  blocks.forEach(function (block) {
    var pre = block.closest('pre');
    var container = document.createElement('div');
    container.className = 'mermaid';
    container.textContent = block.textContent;
    pre.replaceWith(container);
  });

  mermaid.run({ querySelector: '.mermaid' });
})();
