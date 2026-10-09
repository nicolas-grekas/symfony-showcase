import { Controller } from '@hotwired/stimulus';

/*
 * Adds an entry to a PolymorphicCollectionType from the prototype of the requested type,
 * which the form theme renders in a data-prototype-<type> attribute of the collection widget.
 */
export default class extends Controller {
    static values = { index: Number };

    add({ params: { type } }) {
        const widget = this.element.querySelector(`[data-prototype-${type}]`);
        const html = widget.getAttribute(`data-prototype-${type}`).replace(/__name__/g, this.indexValue++);
        widget.insertAdjacentHTML('beforeend', html);
    }
}
