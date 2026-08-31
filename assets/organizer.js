document.addEventListener('alpine:init', () => {

    Alpine.data('organizer', (config = {}) => ({

        items: config.items || [],
        routes: config.routes || {},
        currentPath: config.currentPath || '',

        dragged: null,

        init() {
            console.log('Tree loaded:', this.items);
        },

        dragStart(item) {
            this.dragged = item;
        },

        drop(target) {
            if (!this.dragged || this.dragged === target) return;

            const from = this.items.indexOf(this.dragged);
            const to = this.items.indexOf(target);

            this.items.splice(from, 1);
            this.items.splice(to + 1, 0, this.dragged);
        },

        toggle(item) {
            fetch(this.routes.toggle, {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: "_token=" + encodeURIComponent(csrf()) +
                      "&path=" + encodeURIComponent(item.path)
            });

            item.featured = !item.featured;
        },

        save() {
            fetch(this.routes.save, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": csrf()
                },
                body: JSON.stringify({
                    path: this.currentPath,
                    order: this.items.map(i => i.path)
                })
            }).then(() => {
                alert("Order saved!");
            });
        },

        open(item) {
            window.location = "?path=" + item.path;
        }

    }));
});