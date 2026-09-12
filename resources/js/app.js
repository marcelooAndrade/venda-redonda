/**
 * Reveal ao rolar, só para a apresentação pública.
 *
 * Sem GSAP nem Lenis, de propósito: a marca pede peso de livro-razão, e a
 * decisão original era não animar nada. Aqui a decisão foi revista para
 * movimento sutil, mas a garantia continua: sem JavaScript, ou com
 * `prefers-reduced-motion`, toda seção aparece inteira, sem nada escondido
 * esperando o script.
 */
const revelar = document.querySelectorAll('[data-revelar]');

if (revelar.length > 0) {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !('IntersectionObserver' in window)) {
        revelar.forEach((el) => el.classList.add('revelado'));
    } else {
        const observador = new IntersectionObserver((entradas) => {
            entradas.forEach((entrada) => {
                if (entrada.isIntersecting) {
                    entrada.target.classList.add('revelado');
                    observador.unobserve(entrada.target);
                }
            });
        }, { threshold: 0.15 });

        revelar.forEach((el) => observador.observe(el));
    }
}
