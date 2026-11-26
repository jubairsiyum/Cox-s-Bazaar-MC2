// Chart initialization
// Dashboard charts (resources/views/components/dashboard/*): `<div data-dashboard-chart="<file name in ./dashboard>" data-config='{...}'>`
const dashboardCharts = import.meta.glob("./dashboard/*.js");

export const initCharts = () => {
    document.querySelectorAll("[data-dashboard-chart]").forEach((el) => {
        const load = dashboardCharts[`./dashboard/${el.dataset.dashboardChart}.js`];
        if (load) load().then((module) => module.default(el));
    });
};

export default initCharts;
