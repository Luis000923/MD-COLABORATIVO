(function () {
  if (typeof mermaid === 'undefined') return;

  mermaid.initialize({ startOnLoad: false, theme: 'default' });

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
