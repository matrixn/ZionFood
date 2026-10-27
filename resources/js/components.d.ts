export {};
declare module 'vue' {
    export interface GlobalComponents {
        FieldHelp: (typeof import('./components/ui/FieldHelp.vue'))['default'];
    }
}
