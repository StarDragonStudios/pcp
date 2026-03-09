if (document.readyState === "loading")
    document.addEventListener("DOMContentLoaded", hydrateAll);
else hydrateAll();

const registry = new Map();

export const register = (name, component) => registry.set(name, component);

const parseProps = (node) => {
    const raw = node.getAttribute("data-pcp-props");

    if (!raw) return {};

    try {
        return JSON.parse(raw);
    } catch (err) {
        console.error("PCP: failed to parse props", err);
        return {};
    }
}

const hydrateNode = (node) => {
    const componentName = node.getAttribute("data-pcp-island");

    if (!componentName) return {};

    const component = registry.get(componentName);

    if (!component) {
        console.warn(`PCP: component "${componentName}" not registered`);
        return;
    }

    const props = parseProps(node);

    try {
        component.hydrate(node, props);
    } catch (err) {
        console.error(`PCP: hydration failed for ${componentName}`, err);
    }
}

const hydrateLoad = (node) => hydrateNode(node);

const hydrateIdle = (node) => ("requestIdleCallback" in window)
    ? requestIdleCallback(() => hydrateNode(node))
    : setTimeout(() => hydrateNode(node), 200);

const hydrateVisible = (node) => {
    const observer = new IntersectionObserver(entries => {
        for (const entry of entries) {
            if (entry.isIntersecting) {
                hydrateNode(node);
                observer.disconnect();
                break;
            }
        }
    });

    observer.observe(node);
}

const hydrateInteraction = (node) => {
    const handler = () => {
        hydrateNode(node);
        node.removeEventListener("click", handler);
        node.removeEventListener("focus", handler);
    };

    node.addEventListener("click", handler);
    node.addEventListener("focus", handler);
}

const hydrateByStrategy = (node) => {
    const strategy = node.getAttribute("data-pcp-strategy") || "load";

    switch (strategy) {
        case "load":
            hydrateLoad(node);
            break;

        case "idle":
            hydrateIdle(node);
            break;

        case "visible":
            hydrateVisible(node);
            break;

        case "interaction":
            hydrateInteraction(node);
            break;

        default:
            console.warn("PCP: unknown strategy", strategy);
            hydrateLoad(node);
    }
}

export const hydrateAll = () => {
    const islands = document.querySelectorAll("[data-pcp-island]");

    islands.forEach(node => {
        hydrateByStrategy(node);
    });
}
