export default function loadTwigGrammar(Prism) {
    // Common internal syntax inside Twig tags and variables
    const twigInside = {
        'delimiter': {
            pattern: /^\{[{%#]-?|-?[#%}]\}$/,
            alias: 'punctuation'
        },
        'block-name': {
            pattern: /((?:^|\s+)(?:block|extends|import|from|macro|use)\s+)[a-zA-Z_]\w*/,
            lookbehind: true,
            alias: 'property'
        },
        'string': {
            pattern: /("|')(?:\\.|(?!\1)[^\\\r\n])*\1/,
            greedy: true
        },
        'keyword': /\b(?:extends|block|endblock|set|if|endif|else|elseif|for|endfor|in|macro|endmacro|import|from|include|use|spaceless|endspaceless|filter|endfilter|do|flush|with|without|as|add|endadd|to)\b/,
        'boolean': /\b(?:true|false|null)\b/i,
        'number': /\b0x[\dA-Fa-f]+|(?:\b\d+(?:\.\d*)?|\B\.\d+)(?:[Ee][-+]?\d+)?/,
        'operator': /\b(?:and|or|not|b-and|b-xor|b-or|is(?:\s+not)?|matches|starts\s+with|ends\s+with|same\s+as|default|defined|divisible\s+by|empty|even|iterable|odd)\b|[=<>]=?|!=|\*\*?|\/\/?|\?:?|[-+~%|]/,
        'function': /\b[a-zA-Z_]\w*(?=\s*\()/,
        'filter': {
            pattern: /(\|)\s*[a-zA-Z_]\w*/,
            lookbehind: true,
            alias: 'function'
        },
        'property': /\b[a-zA-Z_]\w*\b/,
        'punctuation': /[()\[\]{}:.,]/
    };

    // We extend 'markup' (HTML) so that standard HTML tags are preserved outside Twig constructs.
    // If 'markup' isn't available, we fallback to an empty grammar.
    Prism.languages.twig = Prism.languages.markup ? Prism.languages.extend('markup', {}) : {};

    // By using `insertBefore` and `greedy: true`, Prism extracts Twig tags correctly
    // even if they appear inside HTML attributes or script tags!
    Prism.languages.insertBefore('twig', 'tag', {
        'twig-comment': {
            pattern: /\{#[\s\S]*?#\}/,
            alias: 'comment',
            greedy: true
        },
        'twig-tag': {
            pattern: /\{%[\s\S]*?%\}/,
            alias: 'statement',
            inside: twigInside,
            greedy: true
        },
        'twig-variable': {
            pattern: /\{\{[\s\S]*?\}\}/,
            alias: 'expression',
            inside: twigInside,
            greedy: true
        }
    });
}
