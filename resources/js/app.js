/**
 * Sessão expirada, em qualquer tela que use Livewire.
 *
 * Por padrão o Livewire mostra um `confirm()` nativo do navegador, em
 * inglês, perguntando se quer recarregar a página. Quem lê isso sem saber
 * o que é Livewire não entende, e o `confirm()` nem sempre é óbvio de achar
 * atrás do diálogo do próprio navegador. `preventDefault()` desliga esse
 * comportamento.
 *
 * O que assume o lugar é recarregar a própria página, e não mandar direto
 * para o login: um 419 é o token da página não bater mais com o da sessão,
 * o que não é a mesma coisa que estar deslogado. Testado local com uma
 * sessão ainda válida (token só ficou velho): mandar para `/login` batia no
 * `RedirectIfAuthenticated` e voltava pro painel em silêncio, sem o usuário
 * ver nada. Recarregando a própria URL, os dois casos se resolvem sozinhos:
 * se a sessão segue válida, a página volta com token novo e dá para tentar
 * de novo; se realmente expirou, o middleware de autenticação já manda para
 * o login, como manda para qualquer tela protegida sem sessão.
 *
 * Isso não resolve a causa do 419 em si, só garante que ninguém fica preso
 * numa tela que não explica o que aconteceu. O que estiver digitado no
 * formulário se perde no recarregamento: é o preço de a página precisar
 * mesmo de um token novo para continuar funcionando.
 */
document.addEventListener('livewire:init', () => {
    Livewire.hook('request', ({ fail }) => {
        fail(({ status, preventDefault }) => {
            if (status === 419) {
                preventDefault();
                window.location.reload();
            }
        });
    });
});

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
