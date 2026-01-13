const mongoose = require('mongoose');
const User = require('./models/User');
const config = require('./config/config');

async function main() {
  try {
    await mongoose.connect(config.MONGO_URI, {
      useNewUrlParser: true,
      useUnifiedTopology: true,
    });

    const username = 'Admin';
    const password = 'Admin_123';

    const usernameLower = username.toLowerCase();
    let admin = await User.findOne({ username: usernameLower }).setOptions({ skipFilter: true });

    if (admin) {
      console.log('Admin user already exists, updating credentials...');
      admin.username = usernameLower;
      admin.email = 'admin@gmail.com';
      admin.password = password;
      admin.role = 'admin';
      admin.isApproved = true;
      admin.isDeleted = false;
      admin.deletedAt = undefined;
    } else {
      admin = new User({
        username,
        email: 'admin@gmail.com',
        password,
        role: 'admin',
        isApproved: true,

        firstName: 'System',
        middleName: '',
        lastName: 'Administrator',
        studentId: 'ADMIN-0001',
        age: 30,
        address: 'System Address',
        gender: 'Other',
      });
    }

    await admin.save();
    console.log('Admin user is ready with username:', username, 'and password:', password);
    process.exit(0);
  } catch (err) {
    console.error('Error creating admin user:', err);
    process.exit(1);
  }
}

main();
