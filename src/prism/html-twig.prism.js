import {Prism} from "prism-react-renderer";

/**
 * Prism.js HTML-Twig Language Definition
 *
 * This definition is designed to flawlessly highlight Twig syntax embedded within HTML.
 * It is built from scratch to be robust and accurate, addressing the common bugs
 * found in default highlighters.
 *
 * @language html-twig
 * @author Gemini
 */

Prism.languages['html-twig'] = Prism.languages.extend('html', {});

// The core Twig grammar for parsing expressions and statements.
// This is designed to be injected inside of the Twig delimiters.
var twigGrammar = {
    // Twig keywords, including control structures, tests, and special constants.
    // The \b boundaries are crucial to prevent matching partial words.
    'keyword': {
        pattern: /\b(?:and|apply|autoescape|block|deprecated|do|embed|endapply|endautoescape|endblock|endembed|endfor|verbatim|endverbatim|endwith|extends|filter|flush|for|from|if|import|include|is|macro|not|or|sandbox|set|use|with|true|false|null)\b/,
        alias: 'keyword'
    },
    // A Twig filter, which starts with a pipe character.
    'filter': {
        pattern: /(\|)\s*[a-zA-Z_][a-zA-Z0-9_]*/,
        lookbehind: true,
        alias: 'function' // Filters are stylistically similar to functions.
    },
    // A Twig function call.
    'function': {
        pattern: /\b[a-zA-Z_][a-zA-Z0-9_]*(?=\()/,
        alias: 'function'
    },
    // Variable names, including object properties (e.g., user.name).
    'variable': {
        pattern: /\b[a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*/,
        alias: 'variable'
    },
    // Standard operators.
    'operator': {
        pattern: /([=<>]=?|!=|\*\*?|\/\/?|\?\?|\?:|%|[-+~?:.,]|\.{2})/,
        alias: 'operator'
    },
// Punctuation characters.
    'punctuation': {
        pattern: /[()\[\]{}:,]/,
        alias: 'punctuation'
    },
// String literals (both single and double quoted).
    'string': {
        pattern: /(["'])(?:(?!\1)[^\\\r\n]|\\.)*\1/,
        greedy: true,
        alias: 'string'
    },
// Numbers.
    'number': {
        pattern: /\b\d+(?:\.\d+)?\b/,
        alias: 'number'
    }
};

// Inject the Twig grammar into the HTML language definition.
// We are inserting it before the 'tag' rule to ensure Twig blocks are processed first.
Prism.languages.insertBefore('html-twig', 'tag', {
    // Twig Comment: {# ... #}
    'twig-comment': {
        pattern: /\{#[\s\S]*?#}/,
        greedy: true,
        alias: 'comment'
    },
    // Twig Tag Block: {% ... %}
    'twig-tag': {
        pattern: /\{%[\s\S]*?%}/,
        greedy: true,
        inside: {
            // The delimiters themselves (e.g., {% or %})
            'delimiter': {
                pattern: /^\{%-?|-?%}$/,
                alias: 'punctuation'
            },
            // The rest of the content is parsed by our core Twig grammar.
            'rest': twigGrammar
        }
    },
    // Twig Output Block: {{ ... }}
    'twig-output': {
        pattern: /\{\{[\s\S]*?}}/,
        greedy: true,
        inside: {
            // The delimiters themselves (e.g., {{ or }})
            'delimiter': {
                pattern: /^\{\{-?|-?}}$/,
                alias: 'punctuation'
            },
            // The rest of the content is also parsed by our core Twig grammar.
            'rest': twigGrammar
        }
    }
});