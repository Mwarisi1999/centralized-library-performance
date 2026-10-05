import { drawRadial } from './charts/apex-theme';

// Every <x-dashboard.gauge-card> renders a server-side percentage marked with data-gauge.
document.querySelectorAll('[data-gauge]').forEach((element) => drawRadial(element, element.dataset.rate));
