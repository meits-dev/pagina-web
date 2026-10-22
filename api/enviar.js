// Recibe el formulario de contacto y lo manda a tu correo con Resend (resend.com, gratis).
//
// Variables en Vercel (Project > Settings > Environment Variables):
//   RESEND_API_KEY = re_xxxxxxxx                       (obligatoria)
//   MAIL_TO        = tu-correo@gmail.com               (obligatoria; a dónde te llegan los mensajes)
//   RESEND_FROM    = Meits Web <onboarding@resend.dev> (opcional; con dominio verificado: Meits <hola@meits.com.mx>)
//
// Para comprobar: abre  https://TU-DOMINIO/api/enviar  -> debe decir "configurado": true
module.exports = async (req, res) => {
  res.setHeader("Content-Type", "application/json; charset=utf-8");
  res.setHeader("Cache-Control", "no-store");

  const { RESEND_API_KEY, MAIL_TO, RESEND_FROM = "Meits Web <onboarding@resend.dev>" } = process.env;
  const listo = !!(RESEND_API_KEY && MAIL_TO);

  if (req.method === "GET") {
    return res.status(200).json({
      ok: true,
      configurado: listo,
      RESEND_API_KEY: !!RESEND_API_KEY,
      MAIL_TO: MAIL_TO || null,
      RESEND_FROM,
      ayuda: listo ? "Listo para enviar." : "Falta RESEND_API_KEY y/o MAIL_TO en Vercel (Settings > Environment Variables). Después haz Redeploy.",
    });
  }
  if (req.method !== "POST") return res.status(405).json({ ok: false, error: "metodo" });

  let b = req.body;
  if (typeof b === "string") { try { b = JSON.parse(b); } catch (e) { b = {}; } }
  b = b || {};
  if (b.web) return res.status(200).json({ ok: true }); // trampa anti-spam

  const lim = (v, m) => String(v || "").replace(/[\r\0]/g, "").trim().slice(0, m);
  const nombre = lim(b.nombre, 120), empresa = lim(b.empresa, 120), contacto = lim(b.contacto, 160);
  const temas = lim(b.temas, 300), mensaje = lim(b.mensaje, 4000);
  if (!contacto || (!mensaje && !temas)) return res.status(422).json({ ok: false, error: "datos" });
  if (!listo) return res.status(500).json({ ok: false, error: "sin_configurar" });

  const replyTo = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contacto) ? contacto : undefined;
  const asunto = `Contacto desde la web de Meits${nombre ? " · " + nombre : ""}`;
  const tel = contacto.replace(/\D/g, "");
  const texto = `Nombre: ${nombre}\nEmpresa: ${empresa}\nContacto: ${contacto}\nLe interesa: ${temas}\n\nMensaje:\n${mensaje}\n` +
    (!replyTo && tel.length >= 10 ? `\nResponder por WhatsApp: https://wa.me/${tel.length === 10 ? "52" + tel : tel}\n` : "");

  try {
    const r = await fetch("https://api.resend.com/emails", {
      method: "POST",
      headers: { Authorization: "Bearer " + RESEND_API_KEY, "Content-Type": "application/json" },
      body: JSON.stringify({ from: RESEND_FROM, to: [MAIL_TO], subject: asunto, text: texto, reply_to: replyTo }),
    });
    if (r.ok) return res.status(200).json({ ok: true });
    console.error("Resend:", r.status, (await r.text()).slice(0, 300));
    return res.status(500).json({ ok: false, error: r.status === 401 || r.status === 403 ? "credenciales" : "envio_fallido" });
  } catch (e) {
    console.error("Resend fallo:", e && e.message);
    return res.status(500).json({ ok: false, error: "envio_fallido" });
  }
};
