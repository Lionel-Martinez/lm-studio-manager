// Evita la ventana de consola extra en Windows (no afecta a Linux).
#![cfg_attr(not(debug_assertions), windows_subsystem = "windows")]

fn main() {
    lm_studio_manager_lib::run()
}
