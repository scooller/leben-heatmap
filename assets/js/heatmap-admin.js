(function ($) {
    const S = {
        page: null,
        points: [],
        docW: 0,
        docH: 0,
        canvas: null,
        ctx: null,
        screenshotWidth: 0,
        screenshotHeight: 0,
        scale: 0.5
    };

    const els = {};

    function init() {
        els.pageSelect = $('#hm-page-select');
        els.dateFrom = $('#hm-date-from');
        els.dateTo = $('#hm-date-to');
        els.refresh = $('#hm-refresh');
        els.exportImg = $('#hm-export-img');
        els.exportCsv = $('#hm-export-csv');
        els.testData = $('#hm-test-data');
        els.screenshot = $('#hm-page-screenshot');
        els.hm = $('#hm-heatmap');
        els.stats = $('#hm-stats');
        els.screenshotStatus = $('#hm-screenshot-status');
        els.scaleSlider = $('#hm-scale-slider');
        els.scaleValue = $('#hm-scale-value');
        els.deviceType = $('#hm-device-type');

        S.canvas = document.createElement('canvas');
        S.ctx = S.canvas.getContext('2d');
        S.canvas.style.position = 'absolute';
        S.canvas.style.top = '0';
        S.canvas.style.left = '0';
        S.canvas.style.pointerEvents = 'none';
        S.canvas.style.zIndex = '10';
        els.hm.append(S.canvas);

        loadPages();

        els.refresh.on('click', loadPoints);
        els.pageSelect.on('change', function () {
            loadPoints();
            loadScreenshot();
        });
        els.deviceType.on('change', function () {
            loadPoints();
            loadScreenshot();
        });

        els.exportImg.on('click', exportImage);
        els.exportCsv.on('click', exportCsv);
        els.testData.on('click', generateTestData);
        
        els.scaleSlider.on('input', function() {
            const scalePercent = parseInt($(this).val());
            S.scale = scalePercent / 100;
            els.scaleValue.text(scalePercent + '%');
            applyScale();
        });

        // Establecer fecha "hasta" al día de hoy por defecto
        const today = new Date().toISOString().split('T')[0];
        els.dateTo.val(today);
    }

    function loadPages() {
        console.log('Loading pages...', {
            ajaxUrl: HeatmapLebenAdmin.ajaxUrl,
            nonce: HeatmapLebenAdmin.nonce
        });

        $.post(HeatmapLebenAdmin.ajaxUrl, {
            action: 'hm_leben_list_pages',
            nonce: HeatmapLebenAdmin.nonce
        })
            .done(res => {
                console.log('Pages response:', res);

                if (!res || !res.success) {
                    console.error('No pages found or request failed');
                    return;
                }

                const list = res.data || [];
                console.log('Populated pages:', list);

                els.pageSelect.empty();

                if (list.length === 0) {
                    $('<option>')
                        .val('')
                        .text('(Sin datos)')
                        .appendTo(els.pageSelect);
                } else {
                    list.forEach(r => {
                        $('<option>')
                            .val(r.page_url)
                            .text(r.page_url + ' (' + r.c + ' eventos)')
                            .appendTo(els.pageSelect);
                    });

                    els.pageSelect.val(list[0].page_url);
                    loadPoints();
                    loadScreenshot();
                }
            })
            .fail((xhr, status, err) => {
                console.error('AJAX failed:', { status, err, response: xhr.responseText });
            });
    }

    function loadPoints() {
        const page_url = els.pageSelect.val();
        if (!page_url) {
            console.log('No page selected');
            return;
        }

        const from = els.dateFrom.val();
        const to = els.dateTo.val();

        // 🎯 LEE EL FILTRO DE EVENTOS
        const eventType = $('#hm-event-type').val() || 'click';
        const deviceType = els.deviceType.val() || 'all';

        console.log('Loading points:', { page_url, from, to, eventType, deviceType });

        // Mostrar estado de carga
        els.refresh.prop('disabled', true).html('<span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span>Cargando...');
        els.stats.html('<span style="color:#999;">Cargando datos...</span>');

        $.post(HeatmapLebenAdmin.ajaxUrl, {
            action: 'hm_leben_get_points',
            nonce: HeatmapLebenAdmin.nonce,
            page_url,
            from,
            to,
            event_type: eventType,  // 🎯 ENVÍA EL FILTRO AL SERVIDOR
            device_type: deviceType
        })
            .done(res => {
                console.log('Points response:', res);
                if (!res || !res.success) {
                    console.error('Failed to get points');
                    return;
                }

                S.points = res.data || [];
                console.log(`✅ Loaded ${S.points.length} points`);
                computeDocSize();
                updateStats(page_url, from, to);
            })
            .fail((xhr, status, err) => {
                console.error('Points AJAX failed:', { status, err });
                els.stats.html('<span style="color:red;">Error al cargar datos</span>');
            })
            .always(() => {
                // Restaurar botón
                els.refresh.prop('disabled', false).html('Actualizar');
            });
    }

    function loadScreenshot() {
        const page_url = els.pageSelect.val();
        const deviceType = els.deviceType.val() || 'all';
        if (!page_url) return;

        if (els.screenshotStatus) {
            els.screenshotStatus.text('Buscando screenshot...');
        }

        $.post(HeatmapLebenAdmin.ajaxUrl, {
            action: 'hm_leben_get_screenshot',
            nonce: HeatmapLebenAdmin.nonce,
            page_url,
            device_type: deviceType === 'all' ? 'desktop' : deviceType
        })
            .done(res => {
                console.log('Screenshot response:', res);

                if (res && res.success && res.data && res.data.exists) {
                    // Capturar dimensiones del servidor
                    const serverWidth = res.data.width;
                    const serverHeight = res.data.height;
                    console.log('📊 Server dimensions available:', serverWidth, 'x', serverHeight);

                    els.screenshot
                        .attr('src', res.data.url)
                        .show()
                        .off('load error') // Prevenir múltiples bindings
                        .on('load', function () {
                            // Intentar obtener dimensiones del navegador
                            const browserWidth = this.naturalWidth || this.width;
                            const browserHeight = this.naturalHeight || this.height;

                            // Si el navegador reporta 0x0 (imagen muy grande), usar dimensiones del servidor
                            if (browserWidth === 0 || browserHeight === 0) {
                                S.screenshotWidth = serverWidth;
                                S.screenshotHeight = serverHeight;
                                console.log('📸 Using server dimensions (browser reported 0x0):', S.screenshotWidth, 'x', S.screenshotHeight);
                            } else {
                                S.screenshotWidth = browserWidth;
                                S.screenshotHeight = browserHeight;
                                console.log('📸 Using browser dimensions:', S.screenshotWidth, 'x', S.screenshotHeight);
                            }

                            // 🎯 RENDER SI HAY PUNTOS
                            if (S.points.length > 0) {
                                computeDocSize();
                                renderHeatmap();
                            }

                            if (els.screenshotStatus) {
                                els.screenshotStatus.text('✓ Screenshot cargado');
                            }
                        })
                        .on('error', function () {
                            console.error('Screenshot failed to load');
                            S.screenshotWidth = 0;
                            S.screenshotHeight = 0;

                            if (els.screenshotStatus) {
                                els.screenshotStatus.text('Error cargando screenshot');
                            }
                        });

                    if (els.screenshotStatus) {
                        els.screenshotStatus.text('✓ Screenshot cargado');
                    }
                } else {
                    els.screenshot.hide();
                    S.screenshotWidth = 0;
                    S.screenshotHeight = 0;

                    if (els.screenshotStatus) {
                        els.screenshotStatus.text('No hay screenshot');
                    }

                    // Render sin screenshot si hay puntos
                    if (S.points.length > 0) {
                        computeDocSize();
                        renderHeatmap();
                    }
                }
            });
    }

    function computeDocSize() {
        // Usar dimensiones reales del screenshot si existe
        if (S.screenshotWidth > 0 && S.screenshotHeight > 0) {
            S.docW = S.screenshotWidth;
            S.docH = S.screenshotHeight;
            console.log('✅ Using screenshot dimensions:', S.docW, 'x', S.docH);
        } else {
            // Obtener dimensiones de la página desde los datos capturados
            let maxPageW = 0, maxPageH = 0;
            for (const p of S.points) {
                const pw = Number(p.page_width || p.viewport_w || 0);
                const ph = Number(p.page_height || 0);
                if (pw > maxPageW) maxPageW = pw;
                if (ph > maxPageH) maxPageH = ph;
            }
            
            // Usar dimensiones reales de la BD o valores por defecto
            S.docW = maxPageW > 0 ? maxPageW : 1920;
            S.docH = maxPageH > 0 ? maxPageH : 4000;
            console.log('📐 Using page dimensions from DB:', S.docW, 'x', S.docH);
        }

        if (S.canvas && els.hm.length) {
            // Canvas nativo (tamaño real)
            S.canvas.width = S.docW;
            S.canvas.height = S.docH;

            console.log('✅ Canvas set to real size:', S.docW, 'x', S.docH);

            // Aplicar escala y renderizar
            applyScale();
            renderHeatmap();
        }
    }


    function applyScale() {
        if (!S.canvas || !els.hm.length || S.docW === 0 || S.docH === 0) {
            return;
        }

        const scaledW = Math.round(S.docW * S.scale);
        const scaledH = Math.round(S.docH * S.scale);

        // Canvas CSS (tamaño visual con escala)
        S.canvas.style.width = scaledW + 'px';
        S.canvas.style.height = scaledH + 'px';

        // Screenshot con escala
        els.screenshot.css({
            width: scaledW + 'px',
            height: scaledH + 'px'
        });

        // Contenedor con escala
        els.hm.css({
            width: scaledW + 'px',
            height: scaledH + 'px'
        });

        console.log('🔧 Scale applied:', S.scale, '| Display size:', scaledW, 'x', scaledH);
    }

    function renderHeatmap() {
        console.log('🚀 renderHeatmap() EXECUTED');
        if (!S.canvas || !S.ctx || !S.points.length || S.canvas.width === 0 || S.canvas.height === 0) {
            console.log('❌ Cannot render');
            return;
        }

        const w = S.canvas.width;
        const h = S.canvas.height;
        const ctx = S.ctx;
        console.log('✅ Canvas ready:', w, 'x', h, '| Points:', S.points.length);
        ctx.clearRect(0, 0, w, h);

        const density = new Float32Array(w * h);
        const radius = 35;
        let debugCount = 0;

        // Calcular escala usando PÁGINA REAL, no viewport
        let totalScaleX = 0, totalScaleY = 0, validPoints = 0;
        for (const raw of S.points) {
            const vw = Number(raw.viewport_w || 1280);
            const ph = Number(raw.page_height || 2560);  // 🎯 USAR PAGE_HEIGHT
            if (vw > 0 && ph > 0) {
            totalScaleX += w / vw;
            totalScaleY += h / ph;
            validPoints++;
            }
        }
        const scaleX = validPoints > 0 ? totalScaleX / validPoints : 1;
        const scaleY = validPoints > 0 ? totalScaleY / validPoints : 1;
        
        console.log('📐 Scale factors:', { scaleX: scaleX.toFixed(3), scaleY: scaleY.toFixed(3), validPoints });

        for (const raw of S.points) {
            const p = {
            x: Number(raw.x),
            y: Number(raw.y),
            scroll_x: Number(raw.scroll_x || 0),
            scroll_y: Number(raw.scroll_y || 0),
            density: Number(raw.density || raw.d || 8)
            };

            // 🎯 SUMAR SCROLL A Y, LUEGO ESCALAR POR PÁGINA REAL
            const posX = p.x * scaleX;
            const posY = (p.y + p.scroll_y) * scaleY;
            
            const finalX = Math.max(0, Math.min(w - 1, Math.round(posX)));
            const finalY = Math.max(0, Math.min(h - 1, Math.round(posY)));

            if (debugCount < 3) {
            console.log('🔍 Point', debugCount, {
                raw: { x: raw.x, y: raw.y, sy: raw.scroll_y },
                combined: { y: (p.y + p.scroll_y).toFixed(1) },
                scaled: { x: posX.toFixed(1), y: posY.toFixed(1) },
                final: { x: finalX, y: finalY }
            });
            debugCount++;
            }

            const val = Math.max(15, Math.min(40, p.density));
            const minX = Math.max(0, finalX - radius);
            const maxX = Math.min(w - 1, finalX + radius);
            const minY = Math.max(0, finalY - radius);
            const maxY = Math.min(h - 1, finalY + radius);
            const r2 = radius * radius;

            for (let y = minY; y <= maxY; y++) {
            for (let x = minX; x <= maxX; x++) {
                const dx = x - finalX;
                const dy = y - finalY;
                const dist2 = dx * dx + dy * dy;
                if (dist2 <= r2) {
                const influence = Math.exp(-dist2 / (2 * radius * radius)) * val;
                density[y * w + x] += influence;
                }
            }
            }
        }

        let maxDensity = 0;
        for (let i = 0; i < density.length; i++) {
            if (density[i] > maxDensity) maxDensity = density[i];
        }
        if (maxDensity === 0) maxDensity = 1;
        console.log('🌡️ Max density:', maxDensity.toFixed(2));

        const getColor = (intensity) => {
            if (intensity < 0.05) return { r: 0, g: 0, b: 0, a: 0 };
            intensity = Math.max(0, Math.min(1, (intensity - 0.05) / 0.95));
            let r, g, b, a;
            if (intensity < 0.25) {
            const t = intensity / 0.25;
            r = 0; g = Math.round(100 + 155 * t); b = 255; a = Math.round(100 + 155 * t);
            } else if (intensity < 0.5) {
            const t = (intensity - 0.25) / 0.25;
            r = Math.round(200 * t); g = 255; b = Math.round(200 - 150 * t); a = 220;
            } else if (intensity < 0.75) {
            const t = (intensity - 0.5) / 0.25;
            r = Math.round(255 * t); g = Math.round(255 - 100 * t); b = 0; a = 255;
            } else {
            r = 255; g = Math.round(155 - 155 * ((intensity - 0.75) / 0.25)); b = 0; a = 255;
            }
            return { r, g, b, a };
        };

        const imgData = ctx.createImageData(w, h);
        const data = imgData.data;
        for (let i = 0; i < density.length; i++) {
            const norm = density[i] / maxDensity;
            const color = getColor(norm);
            const idx = i * 4;
            data[idx] = color.r;
            data[idx + 1] = color.g;
            data[idx + 2] = color.b;
            data[idx + 3] = color.a;
        }

        ctx.putImageData(imgData, 0, 0);
        console.log('✅ HEATMAP RENDERED ✓');
    }

    function updateStats(page_url, from, to) {
        const deviceType = els.deviceType.val() || 'all';
        const eventType = $('#hm-event-type').val() || 'click';

        $.post(HeatmapLebenAdmin.ajaxUrl, {
            action: 'hm_leben_get_stats',
            nonce: HeatmapLebenAdmin.nonce,
            page_url,
            from,
            to,
            device_type: deviceType,
            event_type: eventType
        })
            .done(res => {
                if (!res || !res.success) return;

                const s = res.data || {
                    total: 0, clicks: 0, moves: 0,
                    unique_sessions: 0, returning_sessions: 0
                };

                els.stats.text(
                    `Eventos: ${s.total} | Clicks: ${s.clicks} | Movimientos: ${s.moves} | Usuarios únicos: ${s.unique_sessions} | Recurrentes: ${s.returning_sessions}`
                );
            });
    }

    function exportImage() {
        const canvas = els.hm.find('canvas')[0];
        if (!canvas) return;

        const link = document.createElement('a');
        link.download = 'heatmap.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
    }

    function exportCsv() {
        const page_url = els.pageSelect.val();
        const from = els.dateFrom.val();
        const to = els.dateTo.val();

        const url = `${HeatmapLebenAdmin.ajaxUrl}?action=hm_leben_export_csv` +
            `&nonce=${encodeURIComponent(HeatmapLebenAdmin.nonce)}` +
            `&page_url=${encodeURIComponent(page_url)}` +
            `&from=${encodeURIComponent(from)}` +
            `&to=${encodeURIComponent(to)}`;

        window.location.href = url;
    }

    function generateTestData() {
        if (!confirm('¿Generar 200 eventos de prueba?')) return;

        els.testData.prop('disabled', true).text('Generando...');

        $.post(HeatmapLebenAdmin.ajaxUrl, {
            action: 'hm_leben_generate_test_data',
            nonce: HeatmapLebenAdmin.nonce
        })
            .done(res => {
                console.log('Test data generated:', res);
                if (res && res.success) {
                    alert('✓ ' + res.data.inserted + ' eventos generados');
                    loadPages();
                } else {
                    alert('Error al generar datos');
                }
            })
            .fail(() => alert('Error en AJAX'))
            .always(() => els.testData.prop('disabled', false).text('Generar datos de prueba'));
    }

    $(init);
})(jQuery);
