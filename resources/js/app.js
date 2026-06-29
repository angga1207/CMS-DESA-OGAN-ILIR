import ApexCharts from 'apexcharts';
import L from 'leaflet';
import PhotoSwipeLightbox from 'photoswipe/lightbox';
import Quill from 'quill';
import 'quill/dist/quill.snow.css';
import Swal from 'sweetalert2';
import Swiper from 'swiper';
import { Pagination } from 'swiper/modules';
import TomSelect from 'tom-select';

window.Swal = Swal;

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3500,
    timerProgressBar: true,
});

window.Toast = Toast;

const formatRupiah = (value) => new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 0,
}).format(value);

const initCharts = () => {
    const wrapper = document.querySelector('[data-chart-payload]');

    if (!wrapper) {
        return;
    }

    const payload = JSON.parse(wrapper.dataset.chartPayload || '{}');
    const populationChart = document.querySelector('#populationChart');
    const budgetChart = document.querySelector('#budgetChart');

    if (populationChart && payload.population?.labels?.length) {
        new ApexCharts(populationChart, {
            chart: { type: 'donut', height: 320, toolbar: { show: false } },
            labels: payload.population.labels,
            series: payload.population.values,
            colors: ['#dc2626', '#059669', '#facc15', '#2563eb', '#f97316'],
            legend: { position: 'bottom' },
        }).render();
    }

    if (budgetChart && payload.budget?.labels?.length) {
        new ApexCharts(budgetChart, {
            chart: { type: 'bar', height: 400, toolbar: { show: false } },
            series: [
                { name: 'Rencana', data: payload.budget.planned },
                { name: 'Realisasi', data: payload.budget.realized },
            ],
            xaxis: { categories: payload.budget.labels },
            colors: ['#facc15', '#16a34a'],
            dataLabels: { enabled: false },
            yaxis: { labels: { formatter: (value) => `Rp${formatRupiah(value)}` } },
            tooltip: { y: { formatter: (value) => `Rp${formatRupiah(value)}` } },
        }).render();
    }
};

const initDashboardVisitorChart = () => {
    const wrapper = document.querySelector('[data-dashboard-chart-payload]');
    const chartElement = document.querySelector('#dashboardVisitorChart');

    if (!wrapper || !chartElement || chartElement.dataset.chartReady === '1') {
        return;
    }

    const payload = JSON.parse(wrapper.dataset.dashboardChartPayload || '{}');

    if (!payload.labels?.length) {
        return;
    }

    chartElement.dataset.chartReady = '1';

    new ApexCharts(chartElement, {
        chart: {
            type: 'area',
            height: 300,
            fontFamily: 'Instrument Sans, sans-serif',
            toolbar: { show: false },
            zoom: { enabled: false },
        },
        series: [
            { name: 'Total kunjungan', data: payload.visits },
            { name: 'Pengunjung unik', data: payload.unique },
        ],
        colors: ['#047857', '#84cc16'],
        stroke: { curve: 'smooth', width: [3, 2] },
        fill: {
            type: 'gradient',
            gradient: { shadeIntensity: 0, opacityFrom: 0.3, opacityTo: 0.02, stops: [0, 90, 100] },
        },
        dataLabels: { enabled: false },
        grid: { borderColor: '#f4f4f5', strokeDashArray: 4, padding: { left: 8, right: 12 } },
        xaxis: {
            categories: payload.labels,
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: { style: { colors: '#a1a1aa', fontSize: '11px', fontWeight: 600 } },
        },
        yaxis: {
            min: 0,
            forceNiceScale: true,
            labels: { formatter: (value) => Math.round(value), style: { colors: '#a1a1aa', fontSize: '11px' } },
        },
        legend: { position: 'top', horizontalAlign: 'left', fontSize: '12px', fontWeight: 600, markers: { size: 5 } },
        tooltip: { shared: true, intersect: false, x: { show: true } },
        noData: { text: 'Belum ada data kunjungan' },
    }).render();
};

const initMap = () => {
    const mapElement = document.querySelector('#villageMap');
    const wrapper = document.querySelector('[data-map-points]');

    if (!mapElement || !wrapper) {
        return;
    }

    const points = JSON.parse(wrapper.dataset.mapPoints || '[]');
    const center = points.length ? [points[0].latitude, points[0].longitude] : [-3.295384, 104.674993];
    const map = L.map(mapElement).setView(center, 15);
    const markers = [];

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const renderMarkers = (category = '') => {
        markers.forEach((marker) => marker.remove());
        markers.length = 0;

        points
            .filter((point) => !category || point.category_name === category)
            .forEach((point) => {
                const marker = L.circleMarker([point.latitude, point.longitude], {
                    radius: 9,
                    color: point.color || '#059669',
                    fillColor: point.color || '#059669',
                    fillOpacity: 0.82,
                })
                    .addTo(map)
                    .bindPopup(`<strong>${point.name}</strong><br>${point.category_name || ''}<br>${point.address || ''}`);

                markers.push(marker);
            });
    };

    renderMarkers();

    const select = document.querySelector('#map-category-select');
    if (select) {
        select.addEventListener('change', (event) => renderMarkers(event.target.value));
    }
};

const initCarousel = () => {
    document.querySelectorAll('.hero-banner-carousel').forEach((element) => {
        if (element.dataset.carouselReady === '1') {
            return;
        }

        element.dataset.carouselReady = '1';

        new Swiper(element, {
            modules: [Pagination],
            slidesPerView: 1,
            loop: element.querySelectorAll('.swiper-slide').length > 1,
            pagination: { el: element.querySelector('.swiper-pagination'), clickable: true },
        });
    });

    document.querySelectorAll('.village-carousel').forEach((element) => {
        if (element.dataset.carouselReady === '1') {
            return;
        }

        element.dataset.carouselReady = '1';

        new Swiper(element, {
            modules: [Pagination],
            slidesPerView: 1,
            spaceBetween: 18,
            pagination: { el: element.querySelector('.swiper-pagination'), clickable: true },
            breakpoints: {
                768: { slidesPerView: 2 },
                1024: { slidesPerView: 3 },
            },
        });
    });
};

const initLightbox = () => {
    if (!document.querySelector('#village-gallery')) {
        return;
    }

    const lightbox = new PhotoSwipeLightbox({
        gallery: '#village-gallery',
        children: 'a',
        pswpModule: () => import('photoswipe'),
    });

    lightbox.init();
};

const isTomSelectCandidate = (element) => (
    element.matches('select:not([data-native-select])')
    && !element.closest('.swal2-container')
    && !element.closest('.ql-toolbar')
    && !element.closest('.ql-formats')
    && !element.classList.contains('swal2-select')
    && !Array.from(element.classList).some((className) => className.startsWith('ql-'))
);

const initTomSelect = () => {
    document.querySelectorAll('select:not([data-native-select])').forEach((element) => {
        if (!isTomSelectCandidate(element)) {
            return;
        }

        if (element.tomselect) {
            return;
        }

        new TomSelect(element, {
            allowEmptyOption: true,
            create: false,
            maxOptions: null,
            placeholder: element.dataset.placeholder || element.getAttribute('placeholder') || 'Pilih opsi',
        });
    });
};

let tomSelectObserver;

const observeTomSelectElements = () => {
    if (tomSelectObserver || !document.body) {
        return;
    }

    tomSelectObserver = new MutationObserver((mutations) => {
        const hasNewSelect = mutations.some((mutation) => Array.from(mutation.addedNodes).some((node) => {
            if (!(node instanceof Element)) {
                return false;
            }

            if (isTomSelectCandidate(node)) {
                return !node.tomselect;
            }

            return Array.from(node.querySelectorAll('select:not([data-native-select])'))
                .some((select) => isTomSelectCandidate(select) && !select.tomselect);
        }));

        if (hasNewSelect) {
            queueMicrotask(initTomSelect);
        }
    });

    tomSelectObserver.observe(document.body, {
        childList: true,
        subtree: true,
    });
};

const initSweetAlertConfirmations = () => {
    document.querySelectorAll('[wire\\:confirm]').forEach((element) => {
        const message = element.getAttribute('wire:confirm') || 'Lanjutkan tindakan ini?';

        element.__livewire_confirm = (proceed, cancel) => {
            window.Swal.fire({
                title: 'Konfirmasi tindakan',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, lanjutkan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#52525b',
                reverseButtons: true,
                focusCancel: true,
            }).then((result) => {
                if (result.isConfirmed) {
                    proceed();
                } else {
                    cancel();
                }
            });
        };
    });
};

document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-swal-confirm]');

    if (!form || form.dataset.confirmed === '1') {
        return;
    }

    event.preventDefault();

    window.Swal.fire({
        title: form.dataset.confirmTitle || 'Konfirmasi tindakan',
        text: form.dataset.swalConfirm,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: form.dataset.confirmButton || 'Ya, lanjutkan',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#d97706',
        cancelButtonColor: '#52525b',
        reverseButtons: true,
        focusCancel: true,
    }).then((result) => {
        if (result.isConfirmed) {
            form.dataset.confirmed = '1';
            form.submit();
        }
    });
});

const uploadEditorImage = (file) => new Promise((resolve, reject) => {
    const uploadUrl = document.querySelector('meta[name="editor-upload-url"]')?.content;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!uploadUrl || !csrfToken) {
        reject(new Error('Konfigurasi upload editor belum tersedia.'));
        return;
    }

    const formData = new FormData();
    formData.append('file', file);

    fetch(uploadUrl, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            Accept: 'application/json',
        },
        body: formData,
    })
        .then((response) => response.ok ? response.json() : Promise.reject(response))
        .then((json) => {
            if (!json?.location) {
                reject(new Error('Response upload tidak valid.'));
                return;
            }

            resolve(json.location);
        })
        .catch(() => reject(new Error('Upload gambar gagal.')));
});

const initQuillEditors = () => {
    document.querySelectorAll('.quill-editor').forEach((editorElement) => {
        if (editorElement.dataset.editorReady === '1') {
            return;
        }

        editorElement.dataset.editorReady = '1';

        const componentId = editorElement.closest('[wire\\:id]')?.getAttribute('wire:id');
        const livewireModel = editorElement.dataset.livewireModel;

        const quill = new Quill(editorElement, {
            theme: 'snow',
            modules: {
                toolbar: {
                    container: [
                        [{ header: [2, 3, false] }],
                        ['bold', 'italic', 'underline'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['link', 'image'],
                        ['clean'],
                    ],
                    handlers: {
                        image() {
                            const input = document.createElement('input');
                            input.type = 'file';
                            input.accept = 'image/*';
                            input.click();

                            input.onchange = () => {
                                const file = input.files?.[0];

                                if (!file) {
                                    return;
                                }

                                uploadEditorImage(file)
                                    .then((url) => {
                                        const range = quill.getSelection(true);
                                        quill.insertEmbed(range.index, 'image', url, 'user');
                                        quill.setSelection(range.index + 1);
                                    })
                                    .catch((error) => {
                                        window.Toast?.fire({
                                            title: error.message,
                                            icon: 'error',
                                        });
                                    });
                            };
                        },
                    },
                },
            },
            placeholder: 'Tulis konten di sini...',
        });

        const sync = () => {
            if (componentId && livewireModel && window.Livewire) {
                window.Livewire.find(componentId)?.set(livewireModel, quill.root.innerHTML);
            }
        };

        quill.on('text-change', sync);

        if (editorElement.innerHTML.trim()) {
            sync();
        }
    });
};

const styleQuillEditors = () => {
    document.querySelectorAll('.ql-container').forEach((container) => {
        container.classList.add('min-h-[420px]', 'text-base');
    });

    document.querySelectorAll('.ql-editor').forEach((editor) => {
        editor.classList.add('content-body', 'min-h-[420px]');
    });
};

const initRichTextEditors = () => {
    initQuillEditors();
    styleQuillEditors();
};

const boot = () => {
    initCharts();
    initDashboardVisitorChart();
    initMap();
    initCarousel();
    initLightbox();
    initRichTextEditors();
    initTomSelect();
    observeTomSelectElements();
    initSweetAlertConfirmations();
};

document.addEventListener('DOMContentLoaded', boot);
document.addEventListener('livewire:navigated', boot);
document.addEventListener('livewire:init', () => {
    window.Livewire.hook('morph.updated', () => {
        document.querySelectorAll('select:not([data-native-select])').forEach((element) => {
            if (!element.tomselect) {
                return;
            }

            const value = element.multiple
                ? Array.from(element.selectedOptions).map((option) => option.value)
                : element.value;

            if (JSON.stringify(element.tomselect.getValue()) !== JSON.stringify(value)) {
                element.tomselect.setValue(value, true);
            }
        });

        queueMicrotask(initTomSelect);
    });
});
