import { Controller } from '@hotwired/stimulus';

/*
 * Highlights the rows that a refresh of the page adds, such as a proposal submitted while a reviewer reads the list.
 * A morph moves the rows it keeps, which connects them again: only the ids not seen yet are new.
 */
export default class extends Controller {
    static targets = ['row'];

    connect() {
        this.seen = new Set(this.rowTargets.map((row) => row.id));
    }

    rowTargetConnected(row) {
        if (this.seen && !this.seen.has(row.id)) {
            this.seen.add(row.id);
            row.classList.add('arrived');
        }
    }
}
