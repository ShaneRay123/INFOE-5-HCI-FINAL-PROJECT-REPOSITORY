const AuditLog = require('../models/AuditLog');

async function writeAuditLog(req, { action, entityType, entityId, message, before, after }) {
  try {
    const actor = req.user || {};
    await AuditLog.create({
      actorUserId: actor._id,
      actorUsername: actor.username,
      action,
      entityType,
      entityId: entityId ? String(entityId) : undefined,
      message,
      before,
      after,
      ip: req.ip,
      userAgent: req.headers['user-agent'],
    });
  } catch (e) {
    console.error('[audit] Failed to write audit log:', e);
  }
}

module.exports = {
  writeAuditLog,
};
