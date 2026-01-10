const { PDFDocument, rgb, StandardFonts } = require('pdf-lib');
const fs = require('fs').promises;
const path = require('path');

class CertificateGenerator {
  constructor() {
    this.pageWidth = 842; 
    this.pageHeight = 595; 
    this.margin = 50;
    this.colors = {
      
      background1: rgb(0.102, 0.102, 0.180), 
      background2: rgb(0.086, 0.129, 0.243), 
      background3: rgb(0.059, 0.204, 0.376), 
      primary: rgb(1.0, 0.843, 0.0), 
      secondary: rgb(1.0, 0.647, 0.0), 
      textLight: rgb(0.910, 0.910, 0.910), 
      textGray: rgb(0.831, 0.831, 0.831), 
      textDark: rgb(0.722, 0.722, 0.722), 
      white: rgb(1.0, 1.0, 1.0), 
      darkBlue: rgb(0.102, 0.102, 0.180) 
    };
  }

  async generateCertificate(certificateData) {
    try {
      const pdfDoc = await PDFDocument.create();
      const page = pdfDoc.addPage([this.pageWidth, this.pageHeight]);

      
      const titleFont = await pdfDoc.embedFont(StandardFonts.TimesRomanBold);
      const bodyFont = await pdfDoc.embedFont(StandardFonts.TimesRoman);
      const italicFont = await pdfDoc.embedFont(StandardFonts.TimesRomanItalic);

      
      this.drawGradientBackground(page);
      this.drawGoldenBorder(page);
      this.drawDecorativeElements(page);
      this.drawHeader(page, titleFont, bodyFont);
      this.drawMainContent(page, titleFont, bodyFont, certificateData);
      this.drawAchievementBadge(page, bodyFont, certificateData);
      this.drawFooter(page, titleFont, bodyFont, italicFont, certificateData);
      this.drawBottomQuote(page, italicFont);

      return await pdfDoc.save();
    } catch (error) {
      console.error('Error generating certificate:', error);
      throw new Error('Failed to generate certificate PDF');
    }
  }

  
  drawGradientBackground(page) {
    
    page.drawRectangle({
      x: 0,
      y: 0,
      width: this.pageWidth,
      height: this.pageHeight,
      color: this.colors.background1
    });
    
    
    page.drawRectangle({
      x: 0,
      y: 0,
      width: this.pageWidth * 0.6,
      height: this.pageHeight,
      color: this.colors.background2,
      opacity: 0.6
    });
    
    page.drawRectangle({
      x: 0,
      y: 0,
      width: this.pageWidth * 0.3,
      height: this.pageHeight,
      color: this.colors.background3,
      opacity: 0.4
    });
  }

  
  drawGoldenBorder(page) {
    const borderWidth = 8;
    
    
    page.drawRectangle({
      x: 0,
      y: 0,
      width: this.pageWidth,
      height: this.pageHeight,
      borderColor: this.colors.primary,
      borderWidth: borderWidth,
      color: undefined
    });
    
    
    const innerMargin = 25;
    page.drawRectangle({
      x: innerMargin,
      y: innerMargin,
      width: this.pageWidth - 2 * innerMargin,
      height: this.pageHeight - 2 * innerMargin,
      borderColor: this.colors.primary,
      borderWidth: 3,
      color: undefined,
      opacity: 0.6
    });
    
    
    const innerMargin2 = 35;
    page.drawRectangle({
      x: innerMargin2,
      y: innerMargin2,
      width: this.pageWidth - 2 * innerMargin2,
      height: this.pageHeight - 2 * innerMargin2,
      borderColor: this.colors.secondary,
      borderWidth: 1,
      color: undefined,
      opacity: 0.4
    });
  }

  
  drawDecorativeElements(page) {
    const triangleSize = 80;
    const offset = 20;
    
    
    page.drawRectangle({
      x: offset,
      y: this.pageHeight - offset - triangleSize,
      width: triangleSize,
      height: triangleSize,
      color: this.colors.primary,
      opacity: 0.3
    });
    
    
    page.drawRectangle({
      x: this.pageWidth - offset - triangleSize,
      y: offset,
      width: triangleSize,
      height: triangleSize,
      color: this.colors.primary,
      opacity: 0.3
    });
  }

  
  drawHeader(page, titleFont, bodyFont) {
    
    const logoSize = 50; 
    const logoY = this.pageHeight - 130;
    
    
    page.drawCircle({
      x: this.pageWidth / 2,
      y: logoY,
      size: logoSize,
      color: this.colors.primary
    });
    
    
    page.drawCircle({
      x: this.pageWidth / 2,
      y: logoY,
      size: logoSize - 3,
      borderColor: this.colors.white,
      borderWidth: 3,
      color: undefined
    });
    
    
    const logoText = 'PYlot';
    page.drawText(logoText, {
      x: this.pageWidth / 2 - 32,
      y: logoY - 12,
      size: 24,
      font: titleFont,
      color: this.colors.darkBlue
    });
    
    
    const lineY = logoY - 80;
    page.drawRectangle({
      x: this.pageWidth / 2 - 100,
      y: lineY,
      width: 200,
      height: 3,
      color: this.colors.primary,
      opacity: 0.7
    });
    
    
    const titleText = 'CERTIFICATE';
    const titleY = lineY - 50;
    page.drawText(titleText, {
      x: this.pageWidth / 2 - 120,
      y: titleY,
      size: 42,
      font: titleFont,
      color: this.colors.primary
    });
    
    
    const subtitleText = 'of Completion';
    const subtitleY = titleY - 40;
    page.drawText(subtitleText, {
      x: this.pageWidth / 2 - 85,
      y: subtitleY,
      size: 24,
      font: titleFont,
      color: this.colors.secondary
    });
  }

  
  drawMainContent(page, titleFont, bodyFont, data) {
    let currentY = this.pageHeight - 340; 
    
    
    const presentedText = 'This certificate is proudly presented to';
    page.drawText(presentedText, {
      x: this.pageWidth / 2 - 170,
      y: currentY,
      size: 22,
      font: bodyFont,
      color: this.colors.textLight
    });
    
    currentY -= 45;
    
    
    const nameText = data.fullName;
    const nameSize = 48;
    const nameWidth = nameText.length * nameSize * 0.35;
    page.drawText(nameText, {
      x: this.pageWidth / 2 - (nameWidth / 2),
      y: currentY,
      size: nameSize,
      font: titleFont,
      color: this.colors.primary
    });
    
    
    page.drawRectangle({
      x: this.pageWidth / 2 - 150,
      y: currentY - 12,
      width: 300,
      height: 2,
      color: this.colors.primary
    });
    
    currentY -= 60;
    
    
    const achievementText = 'for outstanding achievement in';
    page.drawText(achievementText, {
      x: this.pageWidth / 2 - 140,
      y: currentY,
      size: 20,
      font: bodyFont,
      color: this.colors.textGray
    });
    
    currentY -= 30;
    
    
    const courseText = 'Python Programming & Development';
    page.drawText(courseText, {
      x: this.pageWidth / 2 - 180,
      y: currentY,
      size: 28,
      font: titleFont,
      color: this.colors.secondary
    });
    
    currentY -= 40;
    
    
    const desc1 = 'Demonstrating mastery of programming concepts,';
    const desc2 = 'problem-solving skills, and software development practices';
    
    page.drawText(desc1, {
      x: this.pageWidth / 2 - 210,
      y: currentY,
      size: 16,
      font: bodyFont,
      color: this.colors.textDark
    });
    
    currentY -= 20;
    
    page.drawText(desc2, {
      x: this.pageWidth / 2 - 230,
      y: currentY,
      size: 16,
      font: bodyFont,
      color: this.colors.textDark
    });
  }

  
  drawAchievementBadge(page, bodyFont, data) {
    const badgeY = 160; 
    const badgeHeight = 50;
    const badgeWidth = 400;
    
    
    page.drawRectangle({
      x: this.pageWidth / 2 - badgeWidth / 2,
      y: badgeY,
      width: badgeWidth,
      height: badgeHeight,
      color: this.colors.primary,
      opacity: 0.1
    });
    
    
    page.drawRectangle({
      x: this.pageWidth / 2 - badgeWidth / 2,
      y: badgeY,
      width: badgeWidth,
      height: badgeHeight,
      borderColor: this.colors.primary,
      borderWidth: 1,
      color: undefined,
      opacity: 0.3
    });
    
    
    const achievementLevel = data.postAssessmentScore >= 90 ? 'Excellence' : 
                            data.postAssessmentScore >= 80 ? 'Distinction' : 'Merit';
    const levelText = `Achievement Level: ${achievementLevel}`;
    page.drawText(levelText, {
      x: this.pageWidth / 2 - 90,
      y: badgeY + 25,
      size: 16,
      font: bodyFont,
      color: this.colors.secondary
    });
    
    
    const completionText = `Completed on ${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}`;
    page.drawText(completionText, {
      x: this.pageWidth / 2 - 100,
      y: badgeY + 5,
      size: 12,
      font: bodyFont,
      color: this.colors.textGray
    });
  }

  
  drawFooter(page, titleFont, bodyFont, italicFont, data) {
    const footerY = 100; 
    
    
    const certIdText = `Certificate ID: ${data.certificateId || 'PYL-' + Date.now().toString().slice(-6)}`;
    page.drawText(certIdText, {
      x: 80,
      y: footerY + 10,
      size: 10,
      font: bodyFont,
      color: this.colors.textDark
    });
    
    const trainingHours = data.modulesCompleted ? data.modulesCompleted * 2 : 16;
    const hoursText = `Training Hours: ${trainingHours} hours`;
    page.drawText(hoursText, {
      x: 80,
      y: footerY - 5,
      size: 10,
      font: bodyFont,
      color: this.colors.textDark
    });
    
    
    const sealSize = 40;
    const sealX = this.pageWidth / 2;
    const sealY = footerY;
    
    page.drawCircle({
      x: sealX,
      y: sealY,
      size: sealSize,
      color: this.colors.primary
    });
    
    page.drawCircle({
      x: sealX,
      y: sealY,
      size: sealSize - 3,
      borderColor: this.colors.white,
      borderWidth: 3,
      color: undefined
    });
    
    
    page.drawText('PYLOT', {
      x: sealX - 20,
      y: sealY + 5,
      size: 10,
      font: titleFont,
      color: this.colors.darkBlue
    });
    
    page.drawText('CERTIFIED', {
      x: sealX - 25,
      y: sealY - 5,
      size: 6,
      font: bodyFont,
      color: this.colors.darkBlue
    });
    
    page.drawText(new Date().getFullYear().toString(), {
      x: sealX - 12,
      y: sealY - 15,
      size: 8,
      font: bodyFont,
      color: this.colors.darkBlue
    });
    
    
    const sigX = this.pageWidth - 180;
    
    
    page.drawText('PYlot Learning Academy', {
      x: sigX,
      y: footerY,
      size: 12,
      font: bodyFont,
      color: this.colors.textGray
    });
  }

  
  drawBottomQuote(page, italicFont) {
    const quoteText = '"Excellence in Education, Innovation in Learning"';
    page.drawText(quoteText, {
      x: this.pageWidth / 2 - 160,
      y: 80,
      size: 10,
      font: italicFont,
      color: rgb(0.533, 0.533, 0.533), 
    });
  }

  
  static calculateEligibility(userProgress) {
    const hasPreAssessment = userProgress.hasCompletedPreAssessment && userProgress.preAssessmentScore !== null;
    const hasPostAssessment = userProgress.hasCompletedPostAssessment && userProgress.postAssessmentScore !== null;
    const hasCompletedModules = userProgress.completedModules && userProgress.completedModules.length > 0;
    
    return {
      isEligible: hasPreAssessment && hasPostAssessment && hasCompletedModules,
      requirements: {
        preAssessment: hasPreAssessment,
        postAssessment: hasPostAssessment,
        modulesCompleted: hasCompletedModules
      }
    };
  }

  
  static prepareCertificateData(user, userProgress, totalModules = 0) {
    const improvementScore = userProgress.postAssessmentScore - userProgress.preAssessmentScore;
    const modulesCompleted = userProgress.completedModules ? userProgress.completedModules.length : 0;
    const completionPercentage = totalModules > 0 ? Math.round((modulesCompleted / totalModules) * 100) : 0;

    return {
      username: user.username,
      fullName: user.fullName,
      preAssessmentScore: userProgress.preAssessmentScore,
      postAssessmentScore: userProgress.postAssessmentScore,
      improvementScore: improvementScore,
      modulesCompleted: modulesCompleted,
      totalModules: totalModules,
      completionPercentage: completionPercentage,
      completionDate: userProgress.postAssessmentCompletedAt || new Date(),
      courseName: 'Python Programming & Development',
      certificateId: `CERT-${Date.now()}-${Math.random().toString(36).substr(2, 9).toUpperCase()}` 
    };
  }
}

module.exports = CertificateGenerator;
