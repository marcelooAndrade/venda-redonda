const P = {
  // Vermelho RCM. 600 = cor da marca medida; 700 = hover medido no site.
  primary: { 50:'#FEF2F3',100:'#FCE0E3',200:'#F9C3C9',300:'#F49AA4',400:'#EE6575',
             500:'#EB3A4F',600:'#E8192C',700:'#B91C1C',800:'#8F1519',900:'#6B1214',900.5:'',950:'#3D0809' },
  // Grafite industrial. 800 = rcm-charcoal, 900 = rcm-black, ambos medidos.
  graphite:{ 50:'#F6F6F6',100:'#E8E8E8',200:'#D1D1D1',300:'#B0B0B0',400:'#8A8A8A',
             500:'#6D6D6D',600:'#555555',700:'#3E3E3E',800:'#2A2A2A',900:'#1A1A1A',950:'#0F0F0F' },
  // Azul-aco derivado do hero (matiz 195-210, 25,8% dos pixels). Uso informativo.
  steel:   { 50:'#F2F6F8',100:'#E1ECF0',200:'#C4D9E1',300:'#9FBFCB',400:'#7D97A3',
             500:'#5F7F8D',600:'#4B6875',700:'#3D5561',800:'#34474F',900:'#2D3C43',950:'#1A252A' },
  // Ambar derivado da foto de fundicao (metal fundido, matiz 30-45). Vira o warning.
  ember:   { 50:'#FDF8ED',100:'#F9EDD0',200:'#F2D89C',300:'#E8BC5F',400:'#DDA132',
             500:'#C4861C',600:'#9E6C17',700:'#7C5314',800:'#5F4014',900:'#4A3212',950:'#2A1C09' },
  success: { 50:'#F0FAF4',100:'#DBF2E3',200:'#B9E5C9',300:'#88D0A5',400:'#4FB47B',
             500:'#2C9760',600:'#1E7A4C',700:'#1A613E',800:'#174D33',900:'#14402B',950:'#0A2417' },
  danger:  { 50:'#FDF3F2',100:'#FAE3E0',200:'#F5C9C4',300:'#EBA49C',400:'#DC7266',
             500:'#C74A3C',600:'#B3261E',700:'#8F1E17',800:'#741C16',900:'#611B16',950:'#360B08' },
};
delete P.primary['900.5'];

const hex2rgb = h => [1,3,5].map(i => parseInt(h.slice(i,i+2),16));
const lum = h => { const [r,g,b] = hex2rgb(h).map(v=>{v/=255;return v<=0.03928?v/12.92:Math.pow((v+0.055)/1.055,2.4);});
  return 0.2126*r+0.7152*g+0.0722*b; };
const ratio = (a,b) => { const [x,y]=[lum(a),lum(b)].sort((m,n)=>n-m); return (x+0.05)/(y+0.05); };
const fmt = r => r.toFixed(2).padStart(5);
const nota = (r,grande=false) => { const min = grande?3:4.5; return r>=7?'AAA':r>=min?'AA ':'FALHA'; };

console.log('=== ESCALAS ===');
for (const [nome,esc] of Object.entries(P)) {
  console.log('\n'+nome);
  console.log('  '+Object.entries(esc).map(([k,v])=>`${k}:${v}`).join('  '));
}

console.log('\n\n=== CONTRASTE: TEXTO SOBRE FUNDO (WCAG AA = 4.5 normal / 3.0 grande) ===');
const pares = [
  ['Texto principal claro','#1A1A1A','#FFFFFF'],
  ['Texto secundario claro','#555555','#FFFFFF'],
  ['Texto terciario claro','#6D6D6D','#FFFFFF'],
  ['Texto sobre cinza 50','#1A1A1A','#F6F6F6'],
  ['Texto claro sobre escuro','#FFFFFF','#1A1A1A'],
  ['Texto claro sobre charcoal','#FFFFFF','#2A2A2A'],
  ['--- BOTOES ---','',''],
  ['Branco sobre primary-600 (marca)','#FFFFFF','#E8192C'],
  ['Branco sobre primary-700 (hover)','#FFFFFF','#B91C1C'],
  ['Branco sobre danger-600','#FFFFFF','#B3261E'],
  ['Branco sobre success-600','#FFFFFF','#1E7A4C'],
  ['Branco sobre steel-600','#FFFFFF','#4B6875'],
  ['Grafite sobre ember-400','#1A1A1A','#DDA132'],
  ['Branco sobre ember-600','#FFFFFF','#9E6C17'],
  ['--- LINKS E TEXTO COLORIDO EM FUNDO CLARO ---','',''],
  ['primary-600 sobre branco','#E8192C','#FFFFFF'],
  ['primary-700 sobre branco','#B91C1C','#FFFFFF'],
  ['steel-700 sobre branco','#3D5561','#FFFFFF'],
  ['success-700 sobre branco','#1A613E','#FFFFFF'],
  ['danger-700 sobre branco','#8F1E17','#FFFFFF'],
  ['ember-700 sobre branco','#7C5314','#FFFFFF'],
  ['--- BADGES: texto 800 sobre fundo 100 ---','',''],
  ['primary 800/100','#8F1519','#FCE0E3'],
  ['success 800/100','#174D33','#DBF2E3'],
  ['danger 800/100','#741C16','#FAE3E0'],
  ['ember 800/100','#5F4014','#F9EDD0'],
  ['steel 800/100','#34474F','#E1ECF0'],
  ['graphite 800/100','#2A2A2A','#E8E8E8'],
  ['--- SOBRE FUNDO ESCURO (sidebar grafite-900) ---','',''],
  ['primary-400 sobre grafite-900','#EE6575','#1A1A1A'],
  ['steel-300 sobre grafite-900','#9FBFCB','#1A1A1A'],
  ['ember-300 sobre grafite-900','#E8BC5F','#1A1A1A'],
  ['success-300 sobre grafite-900','#88D0A5','#1A1A1A'],
  ['grafite-300 sobre grafite-900','#B0B0B0','#1A1A1A'],
  ['grafite-400 sobre grafite-900','#8A8A8A','#1A1A1A'],
];
let falhas = 0;
for (const [rot,fg,bg] of pares) {
  if (!fg) { console.log('\n'+rot); continue; }
  const r = ratio(fg,bg); const n = nota(r);
  if (n === 'FALHA') falhas++;
  console.log(`  ${n}  ${fmt(r)}  ${rot.padEnd(38)} ${fg} sobre ${bg}`);
}
console.log(`\n>>> ${falhas} falha(s) de AA para texto normal.`);

console.log('\n=== SEPARACAO PRIMARY x DANGER (precisam ser distinguiveis) ===');
console.log('  primary-600 #E8192C  luminancia', lum('#E8192C').toFixed(4));
console.log('  danger-600  #B3261E  luminancia', lum('#B3261E').toFixed(4));
console.log('  contraste entre os dois:', fmt(ratio('#E8192C','#B3261E')));
