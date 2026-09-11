# Certificados de teste

**Nenhum arquivo aqui é um certificado real.** São autoassinados, gerados com
`openssl`, com CNPJ fictício e chave privada descartável. A senha de todos é
`teste123`. Não têm cadeia ICP-Brasil e não servem para nada além dos testes.

| Arquivo | Para quê |
|---|---|
| `valido.pfx` | CNPJ `11222333000181`, vigente. Caminho feliz |
| `outro-cnpj.pfx` | CNPJ `99888777000166`. Deve ser recusado por divergência |
| `vencido.pfx` | Venceu em 01/01/2025. Deve ser recusado |
| `legado.pfx` | Cifrado em RC2-40. O OpenSSL 3 recusa com `error:0308010C ... unsupported`, que é o caso que o sistema precisa explicar ao usuário |

Todos carregam o CNPJ no OID `2.16.76.1.3.3` do `subjectAltName`, que é de onde
a `sped-common` extrai o documento, como no certificado ICP-Brasil de verdade.
