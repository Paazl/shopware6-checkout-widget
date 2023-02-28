import PaazlChangeEventPlugin from "./plugin/paazl-change-event.plugin";

const PluginManager = window.PluginManager;

PluginManager.register('PaazlChangeEvent', PaazlChangeEventPlugin, '#paazl-checkout');
