<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }},
            drawing: false,
            ctx: null,
            init() {
                const canvas = this.$refs.canvas;
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                this.ctx = canvas.getContext('2d');
                this.ctx.scale(ratio, ratio);
                this.ctx.lineWidth = 2.2;
                this.ctx.lineCap = 'round';
                this.ctx.lineJoin = 'round';
                this.ctx.strokeStyle = '#18181b';
            },
            point(e) {
                const r = this.$refs.canvas.getBoundingClientRect();
                return [e.clientX - r.left, e.clientY - r.top];
            },
            start(e) {
                this.drawing = true;
                this.$refs.canvas.setPointerCapture(e.pointerId);
                const [x, y] = this.point(e);
                this.ctx.beginPath();
                this.ctx.moveTo(x, y);
            },
            move(e) {
                if (! this.drawing) return;
                const [x, y] = this.point(e);
                this.ctx.lineTo(x, y);
                this.ctx.stroke();
            },
            end() {
                if (! this.drawing) return;
                this.drawing = false;
                this.state = this.$refs.canvas.toDataURL('image/png');
            },
            clear() {
                const c = this.$refs.canvas;
                this.ctx.clearRect(0, 0, c.width, c.height);
                this.state = null;
            },
        }"
        style="display: flex; flex-direction: column; gap: 6px"
    >
        <canvas
            x-ref="canvas"
            x-on:pointerdown.prevent="start($event)"
            x-on:pointermove.prevent="move($event)"
            x-on:pointerup="end()"
            x-on:pointerleave="end()"
            aria-label="Kotak tanda tangan"
            style="width: 100%; height: 170px; touch-action: none; background: #fafafa; border: 1.5px dashed #a1a1aa; border-radius: 8px; cursor: crosshair"
        ></canvas>
        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #52525b">
            <span x-text="state ? 'Tanda tangan terisi' : 'Tanda tangan dengan jari atau stylus di kotak di atas'"></span>
            <x-filament::link tag="button" type="button" size="sm" color="gray" x-on:click="clear()">Hapus</x-filament::link>
        </div>
    </div>
</x-dynamic-component>
