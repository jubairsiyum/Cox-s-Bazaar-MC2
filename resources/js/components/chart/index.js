// Chart initialization with IntersectionObserver lazy-loading, reserved skeletons, and retry support
const dashboardCharts = import.meta.glob("./dashboard/*.js");

function mountChartElement(el) {
    if (el.dataset.chartLoaded === "1" || el.dataset.chartLoading === "1") {
        return;
    }

    const chartName = el.dataset.dashboardChart;
    const load = dashboardCharts[`./dashboard/${chartName}.js`];
    if (!load) {
        return;
    }

    const wrapper = el.closest("[data-chart-wrapper]") || el.parentElement;
    const skeleton = wrapper?.querySelector("[data-chart-skeleton]");
    const errorBox = wrapper?.querySelector("[data-chart-error]");

    el.dataset.chartLoading = "1";
    if (wrapper) {
        wrapper.setAttribute("aria-busy", "true");
    }
    if (errorBox) {
        errorBox.classList.add("hidden");
        errorBox.classList.remove("flex");
    }
    if (skeleton) {
        skeleton.classList.remove("hidden");
    }

    load()
        .then((module) => {
            module.default(el);
            delete el.dataset.chartLoading;
            if (!el.closest('.rv:not(.in)')) {
                el.dataset.chartLoaded = "1";
                if (skeleton) {
                    skeleton.classList.add("hidden");
                }
                if (wrapper) {
                    wrapper.removeAttribute("aria-busy");
                }
            }
        })
        .catch(() => {
            delete el.dataset.chartLoading;
            if (skeleton) {
                skeleton.classList.add("hidden");
            }
            if (wrapper) {
                wrapper.removeAttribute("aria-busy");
            }
            if (errorBox) {
                errorBox.classList.remove("hidden");
                errorBox.classList.add("flex");
            }
        });
}

export const initCharts = () => {
    const chartEls = Array.from(document.querySelectorAll("[data-dashboard-chart]"));
    if (chartEls.length === 0) {
        return;
    }

    chartEls.forEach((el) => {
        const wrapper = el.closest("[data-chart-wrapper]") || el.parentElement;
        const retryBtn = wrapper?.querySelector("[data-chart-retry]");
        if (retryBtn && !retryBtn.dataset.bound) {
            retryBtn.dataset.bound = "1";
            retryBtn.addEventListener("click", () => mountChartElement(el));
        }
    });

    if (!("IntersectionObserver" in window)) {
        chartEls.forEach(mountChartElement);
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    observer.unobserve(entry.target);
                    mountChartElement(entry.target);
                }
            });
        },
        { rootMargin: "250px 0px" }
    );

    chartEls.forEach((el) => observer.observe(el));
};

export default initCharts;
