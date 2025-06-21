// Interface for map adapters
export default class IMapAdapter {
  // Container is the DOM element or id where the map will be mounted
  constructor(containerId) {
    if (this.constructor === IMapAdapter) {
      throw new Error('Cannot instantiate interface directly.');
    }
  }

  /**
   * Initialize the map
   */
  initMap(center, zoom) {
    throw new Error('initMap() must be implemented');
  }

  /**
   * Destroy the map instance
   */
  destroyMap() {
    throw new Error('destroyMap() must be implemented');
  }
}
