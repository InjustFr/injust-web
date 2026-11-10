const entries = '^assets/[^/]+\\.js$';
const ui = '^assets/vue/components/ui/';
const components = '^assets/vue/components/';
const pages = '^assets/vue/(admin/)?pages/';
const islands = '^assets/vue/site/islands/';
const composables = '^assets/vue/composables/';
const site = '^assets/(site\\.js|vue/site/)';
const admin = '^assets/(admin\\.js|vue/admin/)';
const pure = '^assets/vue/.+\\.js$';
const notPure = [composables, '^assets/vue/i18n/', '(^|/)router\\.js$', '\\.test\\.js$'];
const FRAMEWORK = '(^|/)node_modules/(vue|@vue|vue-router|vue-i18n|reka-ui|@lucide)/';

module.exports = {
    forbidden: [
        {
            name: 'ui-is-leaf',
            severity: 'error',
            from: { path: ui },
            to: { path: ['^assets/vue/(admin|site|pages|layouts)/', '^assets/vue/components/(?!ui/)', '^assets/[^/]+\\.js$'] },
        },
        {
            name: 'components-not-pages',
            severity: 'error',
            from: { path: components },
            to: { path: [pages, islands] },
        },
        {
            name: 'pages-islands-only-from-entries',
            severity: 'error',
            from: { pathNot: [entries, '^assets/vue/admin/router\\.js$', '^assets/vue/(admin/pages|pages|site/islands)/', '\\.test\\.js$'] },
            to: { path: [pages, islands] },
        },
        {
            name: 'pure-is-pure',
            severity: 'error',
            from: { path: pure, pathNot: notPure },
            to: { path: ['\\.vue$', composables, FRAMEWORK] },
        },
        {
            name: 'composables-no-components',
            severity: 'error',
            from: { path: composables },
            to: { path: ['\\.vue$', components, pages, islands, '^assets/vue/layouts/'] },
        },
        {
            name: 'site-not-admin',
            severity: 'error',
            from: { path: site },
            to: { path: admin },
        },
        {
            name: 'admin-not-site',
            severity: 'error',
            from: { path: admin },
            to: { path: site },
        },
        { name: 'no-circular', severity: 'error', from: {}, to: { circular: true } },
        { name: 'not-to-unresolvable', severity: 'error', from: {}, to: { couldNotResolve: true } },
    ],
    options: {
        doNotFollow: { path: 'node_modules' },
        moduleSystems: ['es6'],
        enhancedResolveOptions: { extensions: ['.js', '.vue', '.json'] },
    },
};
