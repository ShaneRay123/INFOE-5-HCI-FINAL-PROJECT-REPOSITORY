const mongoose = require('mongoose');


mongoose.connect('mongodb://localhost:27017/pylot', {
  useNewUrlParser: true,
  useUnifiedTopology: true,
});


const moduleSchema = new mongoose.Schema({
  moduleId: { type: String, required: true },
  title: { type: String, required: true },
  description: { type: String, default: "" },
  content: { type: String, default: "" },
  scoreRange: { 
    min: { type: Number, required: true },
    max: { type: Number, required: true }
  },
  isActive: { type: Boolean, default: true },
  difficulty: { type: String, enum: ['Beginner', 'Mid-intermediate', 'Intermediate'], default: 'Beginner' }
}, { timestamps: true });

const Module = mongoose.model("Module", moduleSchema);

async function migrateModules() {
  try {
    console.log('Starting module migration...');
    const modules = await Module.find({});
    let updatedCount = 0;
    
    for (const module of modules) {
      const updateData = {};
      
      
      if (module.subModuleId !== undefined) {
        updateData.$unset = { subModuleId: 1, order: 1 };
      }
      
      
      if (!module.difficulty) {
        updateData.difficulty = 'Beginner';
      }
      
      if (Object.keys(updateData).length > 0) {
        await Module.findByIdAndUpdate(module._id, updateData);
        updatedCount++;
        console.log(`Migrated module: ${module.title}`);
      }
    }
    
    console.log(`Migration completed. Updated ${updatedCount} modules.`);
    process.exit(0);
  } catch (err) {
    console.error('Migration error:', err);
    process.exit(1);
  }
}

migrateModules();

