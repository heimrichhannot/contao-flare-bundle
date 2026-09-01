// @ts-check
// `@type` JSDoc annotations allow editor autocompletion and type checking
// (when paired with `@ts-check`).
// There are various equivalent ways to declare your Docusaurus config.
// See: https://docusaurus.io/docs/api/docusaurus-config

import { themes as prismThemes } from 'prism-react-renderer';

// This runs in Node.js - Don't use client-side code here (browser APIs, JSX...)

// Docs path of the latest stable version — bump when cutting a new release
// with `npm run docusaurus docs:version <x>` (see AGENTS.md).
const latestDocsPath = '/docs/v0.1/'

/** @type {import('@docusaurus/types').Config} */
const config = {
  title: 'Contao Flare Bundle',
  tagline: 'Filter, List and Reader engine for Contao CMS',
  favicon: 'img/favicon.ico',

  // Future flags, see https://docusaurus.io/docs/api/docusaurus-config#future
  future: {
    v4: true, // Improve compatibility with the upcoming Docusaurus v4
    faster: {
      // The SWC HTML minifier decodes character references, which would undo
      // the spam-protection entity obfuscation on src/pages/imprint.md
      swcHtmlMinimizer: false,
    },
  },

  // Set the production url of your site here
  url: 'https://flare.heimrich-hannot.com',
  trailingSlash: false,
  // The site is served from the root of its own (custom) domain — see
  // static/CNAME for the GitHub Pages custom-domain record.
  baseUrl: '/',

  // GitHub pages deployment config.
  // If you aren't using GitHub pages, you don't need these.
  organizationName: 'heimrichhannot', // Usually your GitHub org/user name.
  projectName: 'contao-flare-bundle', // Usually your repo name.
  deploymentBranch: 'gh-pages', // The branch that GitHub pages will deploy from.

  onBrokenLinks: 'throw',
  markdown: {
    hooks: {
      onBrokenMarkdownLinks: 'warn',
    },
  },

  // Even if you don't use internationalization, you can use this field to set
  // useful metadata like html lang. For example, if your site is Chinese, you
  // may want to replace "en" with "zh-Hans".
  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },

  plugins: [
    [
      '@docusaurus/plugin-client-redirects',
      /** @type {import('@docusaurus/plugin-client-redirects').Options} */
      ({
        // Bare /docs (no version segment) has no page of its own.
        redirects: [
          {
            from: '/docs',
            to: `${latestDocsPath}intro`,
          },
        ],
        // Mirror every latest-stable route to its unversioned /docs/... URL,
        // so bare links and pre-versioning inbound links keep resolving.
        createRedirects(existingPath) {
          if (existingPath.includes(latestDocsPath)) {
            return [existingPath.replace(latestDocsPath, '/docs/')];
          }
          return undefined;
        },
      }),
    ],
  ],

  presets: [
    [
      'classic',
      /** @type {import('@docusaurus/preset-classic').Options} */
      ({
        docs: {
          sidebarPath: './sidebars.js',
          // Please change this to your repo.
          // Remove this to remove the "edit this page" links.
          editUrl:
            'https://github.com/heimrichhannot/contao-flare-bundle/tree/docs/main/',
          lastVersion: '0.1',
          versions: {
            '0.1': {
              label: 'v0.1 (latest)',
              path: 'v0.1',
            },
            current: {
              label: 'v0.2 (next)',
              path: 'v0.2',
            },
          },
        },
        blog: false/*{
          showReadingTime: true,
          feedOptions: {
            type: ['rss', 'atom'],
            xslt: true,
          },
          // Please change this to your repo.
          // Remove this to remove the "edit this page" links.
          editUrl:
            'https://github.com/heimrichhannot/contao-flare-bundle/',
          // Useful options to enforce blogging best practices
          onInlineTags: 'warn',
          onInlineAuthors: 'warn',
          onUntruncatedBlogPosts: 'warn',
        }*/,
        theme: {
          customCss: './src/css/custom.css',
        },
      }),
    ],
  ],

  themeConfig:
    /** @type {import('@docusaurus/preset-classic').ThemeConfig} */
    ({
      // Replace with your project's social card
      image: 'img/docusaurus-social-card.jpg',
      navbar: {
        title: 'FLARE',
        logo: {
          alt: 'Contao Flare Bundle Logo',
          src: 'img/plus.svg',
        },
        items: [
          {
            type: 'docSidebar',
            sidebarId: 'documentationSidebar',
            position: 'left',
            label: 'Documentation',
          },
          /*{to: '/blog', label: 'Blog', position: 'left'},*/
          {
            type: 'docsVersionDropdown',
            position: 'right',
            dropdownActiveClassDisabled: true,
          },
          {
            href: 'https://github.com/heimrichhannot/contao-flare-bundle',
            label: 'GitHub',
            position: 'right',
          },
        ],
      },
      footer: {
        style: 'dark',
        links: [
          {
            title: 'Info',
            items: [
              {
                label: 'Heimrich & Hannot GmbH',
                to: 'https://www.heimrich-hannot.de',
              },
              {
                label: 'Impressum',
                to: '/imprint',
              },
              {
                label: 'Datenschutzerklärung',
                to: 'https://www.heimrich-hannot.de/de/datenschutz',
              },
            ],
          }
        ],
        copyright: `Copyright © ${new Date().getFullYear()} &centerdot; Heimrich & Hannot GmbH`,
      },
      prism: {
        theme: prismThemes.oneLight,
        darkTheme: prismThemes.vsDark,
        additionalLanguages: ['php', 'php-extras', 'phpdoc'],
      },
    }),
};

export default config;
