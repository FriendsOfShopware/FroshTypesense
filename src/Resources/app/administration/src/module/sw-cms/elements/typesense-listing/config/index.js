import template from './template.html.twig';

export default {
    template,

    computed: {
        filterByCategory: {
            get() {
                return this.isActiveFilter('category-filter');
            },
            set(value) {
                this.updateFilters('category-filter', value);
            },
        },
    },
};
