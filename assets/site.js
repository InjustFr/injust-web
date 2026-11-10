import './styles/site/fonts.css';
import './styles/site/tokens.css';
import './styles/site/base.css';

const islands = import.meta.glob('./vue/site/islands/*.vue');

document.querySelectorAll('[data-island]').forEach(async (element) => {
    const load = islands[`./vue/site/islands/${element.dataset.island}.vue`];
    if (!load) {
        return;
    }
    const [{ createApp }, { default: island }] = await Promise.all([import('vue'), load()]);
    createApp(island, JSON.parse(element.dataset.props ?? '{}')).mount(element);
});
