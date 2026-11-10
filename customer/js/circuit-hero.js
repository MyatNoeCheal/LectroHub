/* ══════════════════════════════════════════
   circuit-hero.js
   Three.js "circuit constellation" background
   for the hero canvas (#lhHeroCircuit) on
   index.php.

   Renders a field of drifting points with thin
   connecting lines between nearby points — a
   soft animated circuit/constellation look
   sitting behind the hero copy.

   Defensive by design:
   - Bails quietly if THREE isn't loaded yet
     (wrong script order) or the target div is
     missing (page without a hero).
   - Respects prefers-reduced-motion.
   - Cleans up on page unload.
══════════════════════════════════════════ */
(function () {
    'use strict';

    function initCircuitHero() {
        var container = document.getElementById('lhHeroCircuit');
        if (!container) return; // not on this page

        if (typeof THREE === 'undefined') {
            console.warn('[circuit-hero] THREE is not defined — check that three.min.js loads before circuit-hero.js in head.php.');
            return;
        }

        var prefersReducedMotion = window.matchMedia &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        /* ── Config ── */
        var POINT_COUNT   = 90;
        var CONNECT_DIST  = 18;   // max distance (world units) to draw a line between two points
        var FIELD_RADIUS  = 60;   // spread of the point cloud
        var FIELD_DEPTH   = 40;
        var ACCENT_COLOR  = getAccentColor();

        /* ── Renderer / scene / camera ── */
        var width  = container.clientWidth  || container.offsetWidth  || window.innerWidth;
        var height = container.clientHeight || container.offsetHeight || 480;

        var renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        renderer.setSize(width, height);
        renderer.setClearColor(0x000000, 0);
        container.appendChild(renderer.domElement);

        var scene  = new THREE.Scene();
        var camera = new THREE.PerspectiveCamera(55, width / height, 0.1, 300);
        camera.position.set(0, 0, 85);

        /* ── Points ── */
        var points = [];
        var positions = new Float32Array(POINT_COUNT * 3);

        for (var i = 0; i < POINT_COUNT; i++) {
            var x = (Math.random() - 0.5) * FIELD_RADIUS * 2;
            var y = (Math.random() - 0.5) * FIELD_RADIUS * 1.2;
            var z = (Math.random() - 0.5) * FIELD_DEPTH;

            points.push({
                x: x, y: y, z: z,
                vx: (Math.random() - 0.5) * 0.02,
                vy: (Math.random() - 0.5) * 0.02,
                vz: (Math.random() - 0.5) * 0.01,
            });

            positions[i * 3]     = x;
            positions[i * 3 + 1] = y;
            positions[i * 3 + 2] = z;
        }

        var pointGeometry = new THREE.BufferGeometry();
        pointGeometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));

        var pointMaterial = new THREE.PointsMaterial({
            color: ACCENT_COLOR,
            size: 1.6,
            transparent: true,
            opacity: 0.85,
            sizeAttenuation: true,
        });

        var pointCloud = new THREE.Points(pointGeometry, pointMaterial);
        scene.add(pointCloud);

        /* ── Connecting lines (circuit look) ──
           Rebuilt each frame from the current point positions.
           Capped so we never allocate a runaway number of segments. */
        var MAX_SEGMENTS = POINT_COUNT * 6;
        var linePositions = new Float32Array(MAX_SEGMENTS * 2 * 3);
        var lineGeometry = new THREE.BufferGeometry();
        lineGeometry.setAttribute('position', new THREE.BufferAttribute(linePositions, 3));

        var lineMaterial = new THREE.LineBasicMaterial({
            color: ACCENT_COLOR,
            transparent: true,
            opacity: 0.18,
        });

        var lineSegments = new THREE.LineSegments(lineGeometry, lineMaterial);
        scene.add(lineSegments);

        function rebuildLines() {
            var segCount = 0;
            var connectDistSq = CONNECT_DIST * CONNECT_DIST;

            for (var a = 0; a < points.length && segCount < MAX_SEGMENTS; a++) {
                for (var b = a + 1; b < points.length && segCount < MAX_SEGMENTS; b++) {
                    var dx = points[a].x - points[b].x;
                    var dy = points[a].y - points[b].y;
                    var dz = points[a].z - points[b].z;
                    var distSq = dx * dx + dy * dy + dz * dz;

                    if (distSq < connectDistSq) {
                        var idx = segCount * 6;
                        linePositions[idx]     = points[a].x;
                        linePositions[idx + 1] = points[a].y;
                        linePositions[idx + 2] = points[a].z;
                        linePositions[idx + 3] = points[b].x;
                        linePositions[idx + 4] = points[b].y;
                        linePositions[idx + 5] = points[b].z;
                        segCount++;
                    }
                }
            }

            lineGeometry.setDrawRange(0, segCount * 2);
            lineGeometry.attributes.position.needsUpdate = true;
        }

        /* ── Animation ── */
        var animationId = null;
        var lastFrame = 0;
        var FRAME_INTERVAL = 1000 / 30; // cap at ~30fps, this is a background element

        function tick(now) {
            animationId = requestAnimationFrame(tick);

            if (now - lastFrame < FRAME_INTERVAL) return;
            lastFrame = now;

            for (var i = 0; i < points.length; i++) {
                var p = points[i];
                p.x += p.vx;
                p.y += p.vy;
                p.z += p.vz;

                // Gently bounce back once a point drifts past the field bounds
                if (p.x > FIELD_RADIUS || p.x < -FIELD_RADIUS) p.vx *= -1;
                if (p.y > FIELD_RADIUS * 0.6 || p.y < -FIELD_RADIUS * 0.6) p.vy *= -1;
                if (p.z > FIELD_DEPTH / 2 || p.z < -FIELD_DEPTH / 2) p.vz *= -1;

                positions[i * 3]     = p.x;
                positions[i * 3 + 1] = p.y;
                positions[i * 3 + 2] = p.z;
            }
            pointGeometry.attributes.position.needsUpdate = true;

            rebuildLines();

            // Slow overall drift for a subtle parallax feel
            scene.rotation.y += 0.0006;

            renderer.render(scene, camera);
        }

        if (prefersReducedMotion) {
            // Render a single static frame instead of animating
            rebuildLines();
            renderer.render(scene, camera);
        } else {
            animationId = requestAnimationFrame(tick);
        }

        /* ── Resize handling ── */
        function handleResize() {
            var w = container.clientWidth  || window.innerWidth;
            var h = container.clientHeight || 480;
            if (w === 0 || h === 0) return;

            camera.aspect = w / h;
            camera.updateProjectionMatrix();
            renderer.setSize(w, h);
        }

        window.addEventListener('resize', handleResize);

        /* ── Cleanup ── */
        window.addEventListener('beforeunload', function () {
            if (animationId) cancelAnimationFrame(animationId);
            pointGeometry.dispose();
            lineGeometry.dispose();
            pointMaterial.dispose();
            lineMaterial.dispose();
            renderer.dispose();
        });

        /* ── Helpers ── */
        function getAccentColor() {
            try {
                var css = getComputedStyle(document.documentElement)
                    .getPropertyValue('--accent')
                    .trim();
                if (css) return new THREE.Color(css).getHex();
            } catch (e) {}
            return 0x00e5ff; // fallback to the LectroHub accent cyan
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCircuitHero);
    } else {
        initCircuitHero();
    }
})();